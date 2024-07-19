<?php

declare (strict_types = 1);

namespace Laket\Admin\Database;

use Laket\Admin\Flash\Menu;
use Laket\Admin\Facade\Flash;
use Laket\Admin\Flash\Service as BaseService;

class Service extends BaseService
{
    /**
     * composer
     */
    public $composer = __DIR__ . '/../composer.json';
    
    /**
     * 设置
     */
    public $setting = __DIR__ . '/../resources/config/setting.php';
    
    /**
     * 包名
     */
    protected $pkg = 'laket/laket-database';
    
    /**
     * 权限菜单 slug
     */
    protected $slug = 'laket-admin.flash.database';
    
    /**
     * 启动
     */
    public function boot()
    {
        Flash::extend('laket/laket-database', __CLASS__);
    }
    
    /**
     * 在插件安装、插件卸载等操作时有效
     */
    public function action()
    {
        register_install_hook($this->pkg, [$this, 'install']);
        register_uninstall_hook($this->pkg, [$this, 'uninstall']);
        register_upgrade_hook($this->pkg, [$this, 'upgrade']);
        register_enable_hook($this->pkg, [$this, 'enable']);
        register_disable_hook($this->pkg, [$this, 'disable']);
    }

    /**
     * 开始，只有启用后加载
     */
    public function start()
    {
        // 路由
        $this->loadRoutesFrom(__DIR__ . '/../resources/routes/admin.php');
        
        // 视图
        $this->loadViewsFrom(__DIR__ . '/../resources/view', 'laket-database');
    }
    
    /**
     * 安装后
     */
    public function install()
    {
        $slug = $this->slug;
        $menus = include __DIR__ . '/../resources/menus/menus.php';
        
        // 添加菜单
        Menu::create($menus);
    }
    
    /**
     * 卸载后
     */
    public function uninstall()
    {
        Menu::delete($this->slug);
    }
    
    /**
     * 更新后
     */
    public function upgrade()
    {}
    
    /**
     * 启用后
     */
    public function enable()
    {
        Menu::enable($this->slug);
    }
    
    /**
     * 禁用后
     */
    public function disable()
    {
        Menu::disable($this->slug);
    }
    
}
