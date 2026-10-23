import gzip
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import tarfile
import tempfile
import unittest
from unittest.mock import patch

spec=importlib.util.spec_from_file_location('pull',Path(__file__).with_name('vigilante-replica-pull.py'))
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)


class ReplicaTests(unittest.TestCase):
    def sql(self):
        data=gzip.compress(b'-- SQL textual\n'+b'-- abcdefghijklmnopqrstuvwxyz\n'*100+b'-- Dump completed on fixture\n',compresslevel=0)
        ident=hashlib.sha256(data).hexdigest()
        return data,{'id':ident,'sha256':ident,'dataset':'sql:c1_fixture','kind':'sql','bytes':len(data),'created_at':1,'text_verified':True}

    def remote(self,data,entry,stop=None):
        count=0
        def call(command):
            nonlocal count
            if command=='manifest':return json.dumps({'version':1,'entries':[entry],'omitted':[]}).encode()
            name,ident,offset,length=command.split();assert name=='get' and ident==entry['id']
            count+=1
            if stop and count==stop:raise ConnectionError('interrupcion de ensayo')
            return data[int(offset):int(offset)+int(length)]
        return call

    def test_resume_preserves_previous_and_checks_content(self):
        data,entry=self.sql()
        with tempfile.TemporaryDirectory() as path,patch.object(m,'CHUNK',128):
            root=Path(path);m.directory(root/'objects');old=root/'objects'/'previous.sql.gz';old.write_bytes(b'previous')
            with self.assertRaises(ConnectionError):m.collect(root,self.remote(data,entry,stop=3))
            partial=root/'pending'/(entry['id']+'.partial');self.assertEqual(partial.stat().st_size,256)
            resumed=[];remote=self.remote(data,entry)
            def record(command):
                if command.startswith('get '):resumed.append(int(command.split()[2]))
                return remote(command)
            result=m.collect(root,record)
            self.assertTrue(result['completed']);self.assertEqual(resumed[0],256)
            self.assertFalse(partial.exists());self.assertEqual(old.read_bytes(),b'previous')
            self.assertEqual((root/'objects'/(entry['id']+'.sql.gz')).read_bytes(),data)
            self.assertFalse(result['restoration_tested'])

    def test_bad_digest_never_commits(self):
        data,entry=self.sql();bad=bytearray(data);bad[30]^=1
        with tempfile.TemporaryDirectory() as path:
            root=Path(path)
            with self.assertRaises(ValueError):m.collect(root,self.remote(bytes(bad),entry))
            self.assertFalse(list((root/'objects').iterdir()))
            self.assertFalse(json.loads((root/'status.json').read_text())['completed'])

    def test_rejects_path_and_size_in_catalogue(self):
        _,entry=self.sql()
        for change in [{'id':'../escape'},{'bytes':m.MAX_OBJECT+1},{'kind':'exe'},{'text_verified':False}]:
            with self.assertRaises(ValueError):m.validate({'version':1,'entries':[{**entry,**change}]})

    def test_tar_hashes_and_metadata(self):
        with tempfile.TemporaryDirectory() as path:
            root=Path(path);file=root/'fixture.tar.gz';data=b'texto de ensayo'
            info=tarfile.TarInfo('./private/test.php');info.size=len(data);info.mode=0o640
            with tarfile.open(file,'w:gz') as tar:tar.addfile(info,io.BytesIO(data))
            tree=[{'path':'private/test.php','sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data),'uid':0,'gid':0,'mode':0o640}]
            entry={'bytes':file.stat().st_size,'sha256':m.digest(file),'kind':'text','files':1,
                   'tree_sha256':hashlib.sha256(json.dumps(tree,sort_keys=True,separators=(',',':')).encode()).hexdigest()}
            m.verify(file,entry)
            entry['tree_sha256']='0'*64
            with self.assertRaises(ValueError):m.verify(file,entry)

    def test_offline_retains_last_success(self):
        with tempfile.TemporaryDirectory() as path:
            root=Path(path);m.save(root/'status.json',{'last_success':123})
            def offline(command):raise ConnectionError('sin red')
            with self.assertRaises(ConnectionError):m.collect(root,offline)
            status=json.loads((root/'status.json').read_text())
            self.assertEqual(status['last_success'],123);self.assertTrue(status['pending'])

    def test_missing_source_is_not_complete(self):
        data,entry=self.sql();remote=self.remote(data,entry)
        def omitted(command):
            if command=='manifest':return json.dumps({'version':1,'entries':[entry],'omitted':[{'dataset':'sql:c1_missing'}]}).encode()
            return remote(command)
        with tempfile.TemporaryDirectory() as path:
            result=m.collect(Path(path),omitted);self.assertFalse(result['completed'])


if __name__=='__main__':unittest.main()
