#!/usr/bin/python3
"""Estado agregado por socket Unix; no solicita páginas ni registra URIs."""
import json
import os
import subprocess
import sys
import time
from datetime import datetime, timezone

SOCKET = "/run/php/roleplus-metrics.sock"
DESTINATION = "/var/www/clients/client1/web6/private/var/observability/fpm.jsonl"
FIELDS = ("listen queue", "max listen queue", "idle processes", "active processes",
          "total processes", "max active processes", "max children reached",
          "accepted conn", "slow requests", "start since")

def sample():
    env = {"PATH": "/usr/bin:/bin", "REQUEST_METHOD": "GET",
           "SCRIPT_NAME": "/__roleplus_fpm_metrics", "SCRIPT_FILENAME": "/__roleplus_fpm_metrics",
           "REQUEST_URI": "/__roleplus_fpm_metrics?json", "QUERY_STRING": "json",
           "SERVER_PROTOCOL": "HTTP/1.1"}
    record = {"time_utc": datetime.now(timezone.utc).isoformat(timespec="seconds")}
    try:
        result = subprocess.run(["/usr/bin/cgi-fcgi", "-bind", "-connect", SOCKET],
                                env=env, capture_output=True, timeout=3, check=True)
        raw = result.stdout.decode("utf-8")
        payload = json.loads(raw[raw.index("{"):])
        if payload.get("pool") != "web6":
            raise ValueError("Unexpected pool")
        for field in FIELDS:
            record[field] = int(payload[field])
    except Exception as error:
        record["probe_error"] = type(error).__name__
    return record

def main():
    if "--once" in sys.argv:
        print(json.dumps(sample()))
        return
    while True:
        record = sample()
        with open(DESTINATION, "a", encoding="utf-8") as stream:
            stream.write(json.dumps(record) + "\n")
        time.sleep(5)

if __name__ == "__main__":
    main()
