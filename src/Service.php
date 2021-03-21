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
    
    protected $slug = '';
    
    /**
     * 启动
     */
    public function boot()
    {
        Flash::extend('laket/database', __CLASS__);
        
        $this->slug = 'laket-admin.flash.database';
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
