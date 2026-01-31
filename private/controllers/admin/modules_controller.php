<?php
/**
 */
class ModulesController extends AdminController
{
    public function index($module='')
    {
        $this->modules = (new Modules)->read();
        $this->module_selected = $module;
        $this->module_installed = (new Modules)->isInstalled($module);
        $this->module_readme = (new Modules)->readme($module);
    }

    public function uninstall($module)
    {
        (new Modules)->uninstall($module);

        if (file_exists(APP_PATH . "controllers/admin/$module" . '_controller.php')) {
            Redirect::to("/admin/$module/uninstall");
        }
        else {
            Redirect::to("/admin/modules/index/$module");
        }
    }

    public function install($module)
    {
        (new Modules)->install($module);

        if (file_exists(APP_PATH . "controllers/admin/$module" . '_controller.php')) {
            Redirect::to("/admin/$module/install");
        }
        else {
            Redirect::to("/admin/modules/index/$module");
        }
    }

    public function upload()
    {
        $module = (new Modules)->upload($_FILES['module']);

        Redirect::to("admin/modules/index/$module");
    }
}
