<?php

declare (strict_types = 1);

namespace Laket\Admin\Database\Controller;

use think\facade\Db;
use Laket\Admin\Flash\Controller as BaseController;
use Laket\Admin\Database\Support\Database as DatabaseService;

/**
 * 数据库管理
 * 
 * @create 2021-3-21
 * @author deatil
 */
class Database extends BaseController
{
    // 配置
    protected $databaseConfig = [];
    
    protected function initialize()
    {
        parent::initialize();
        
        // 获取插件配置
        $config = laket_flash_setting('laket/laket-database');
        
        if (empty($config)) {
            $this->error('请先进行相关配置！');
        }
        
        $this->databaseConfig = [
            //数据库备份根路径（路径必须以 / 结尾）
            'path' => root_path() . $config['path'],
            //数据库备份卷大小 （该值用于限制压缩后的分卷最大长度。单位：B；建议设置20M）
            'part' => (int) $config['part'],
            //数据库备份文件是否启用压缩 （压缩备份文件需要PHP环境支持gzopen,gzwrite函数）
            'compress' => (int) $config['compress'],
            //数据库备份文件压缩级别 （数据库备份文件的压缩级别，该配置在开启压缩时生效） 1普通 4一般 9最高
            'level' => (int) $config['level'],
        ];
    }

    /**
     * 数据库
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $list = Db::query('SHOW TABLE STATUS');
            $list = array_map('array_change_key_case', $list); //全部小写

            return json([
                "code" => 0, 
                "data" => $list,
            ]);
        }
        
        return $this->fetch('laket-database::index');
    }

    /**
     * 备份数据库
     */
    public function export()
    {
        // 表名
        $tables = $this->request->param('tables/a');
        // 表ID
        $id = $this->request->param('id/d');
        // 起始行数
        $start = $this->request->param('start/d');
        if ($this->request->isPost() && !empty($tables) && is_array($tables)) {
            // 读取备份配置
            $config = $this->databaseConfig;
            if (!is_dir($config['path'])) {
                mkdir($config['path'], 0755, true);
            }
            
            // 检查是否有正在执行的任务
            $lock = $config['path'] . "backup.lock";
            if (is_file($lock)) {
                // 超过30分钟清除锁定文件，防止后期不能再备份
                $backupMtime = filemtime($lock);
                if (($backupMtime + 1800) < time()) {
                    unlink($lock);
                }
                return $this->error('检测到有一个备份任务正在执行，请稍后再试！');
            }
            
            // 创建锁文件
            file_put_contents($lock, time());
                
            // 检查备份目录是否可写
            if (!is_writeable($config['path'])) {
                return $this->error('备份目录不存在或不可写，请检查后重试！');
            }
            
            session('backup_config', $config);
            
            // 生成备份文件信息
            $file = [
                'name' => date('Ymd-His', time()),
                'part' => 1,
            ];
            
            session('backup_file', $file);
            // 缓存要备份的表
            session('backup_tables', $tables);
            
            // 创建备份文件
            $Database = new DatabaseService($file, $config);
            if (false !== $Database->create()) {
                $tab = [
                    'id' => 0, 
                    'start' => 0,
                ];
                return $this->success('初始化成功！', '', [
                    'tables' => $tables, 
                    'tab' => $tab,
                ]);
            } else {
                return $this->error('初始化失败，备份文件创建失败！');
            }
        } elseif ($this->request->isGet() && is_numeric($id) && is_numeric($start)) {
            // 备份数据
            $tables = session('backup_tables');
            // 备份指定表
            $Database = new DatabaseService(session('backup_file'), session('backup_config'));
            $start = $Database->backup($tables[$id], $start);
            if (false === $start) {
                // 出错
                return $this->error('备份出错！');
            } elseif (0 === $start) {
                // 下一表
                if (isset($tables[++$id])) {
                    $tab = [
                        'id' => $id, 
                        'start' => 0,
                    ];
                    return $this->success('备份完成！', '', [
                        'tab' => $tab, 
                    ]);
                } else {
                    // 备份完成，清空缓存
                    unlink(session('backup_config.path') . 'backup.lock');
                    session('backup_tables', null);
                    session('backup_file', null);
                    session('backup_config', null);
                    return $this->success('备份完成！');
                }
            } else {
                $tab = [
                    'id' => $id, 
                    'start' => $start[0],
                ];
                $rate = floor(100 * ($start[0] / $start[1]));
                return $this->success("正在备份...({$rate}%)", '', [
                    'tab' => $tab, 
                ]);
            }

        } else {
            return $this->error('参数错误！');
        }

    }

    /**
     * 备份恢复
     */
    public function restore()
    {
        if ($this->request->isAjax()) {
            // 列出备份文件列表
            $path = $this->databaseConfig['path'];
            $glob = glob($path . '*.gz', GLOB_BRACE);
            $list = [];
            foreach ($glob as $key => $file) {
                $fileInfo = pathinfo($file);
                // 文件名
                $name = $fileInfo['basename'];
                if (preg_match('/^\d{8,8}-\d{6,6}-\d+\.sql(?:\.gz)?$/', $name)) {
                    $name = sscanf($name, '%4s%2s%2s-%2s%2s%2s-%d');

                    $date = "{$name[0]}-{$name[1]}-{$name[2]}";
                    $time = "{$name[3]}:{$name[4]}:{$name[5]}";
                    $part = $name[6];

                    if (isset($list["{$date} {$time}"])) {
                        $info = $list["{$date} {$time}"];
                        $info['part'] = max($info['part'], $part);
                        $info['size'] = $info['size'] + filesize($file);
                    } else {
                        $info['part'] = $part;
                        $info['size'] = filesize($file);
                    }

                    $extension = strtoupper($fileInfo['extension']);
                    $info['compress'] = ($extension === 'SQL') ? '-' : $extension;
                    $info['date'] = date('Y-m-d H:i:s', strtotime("{$date} {$time}"));
                    $info['time'] = strtotime("{$date} {$time}");
                    $info['title'] = date('Ymd-His', strtotime("{$date} {$time}"));
                    $list[$key] = $info;
                }
            }
            $result = [
                "code" => 0, 
                "data" => $list,
            ];
            return json($result);
        } else {
            return $this->fetch('laket-database::restore');
        }
    }

    /**
     * 下载
     */
    public function download()
    {
        $time = $id = $this->request->param('time/d');
        if ($time) {
            //备份数据库文件名
            $name = date('Ymd-His', $time) . '-*.sql*';
            $path = $this->databaseConfig['path'] . $name;
            $path = glob($path);
            if (empty($path)) {
                $this->error('下载文件不存在！');
            }
            $file = $path[0];
            $file_part = pathinfo($file);

            $basename = $file_part['basename'];
            return download($file, $basename);
        } else {
            $this->error('参数错误！');
        }
    }

    /**
     * 还原数据库
     */
    public function import()
    {
        //时间
        $time = $this->request->param('time', 0, 'intval');
        $part = $this->request->param('part', null);
        //起始行数
        $start = $this->request->param('start', null);
        if (is_numeric($time) && is_null($part) && is_null($start)) {
            //获取备份文件信息
            $name = date('Ymd-His', $time) . '-*.sql*';
            $path = $this->databaseConfig['path'] . $name;
            $files = glob($path);
            $list = [];
            foreach ($files as $name) {
                $basename = basename($name);
                $match = sscanf($basename, '%4s%2s%2s-%2s%2s%2s-%d');
                $gz = preg_match('/^\d{8,8}-\d{6,6}-\d+\.sql.gz$/', $basename);
                $list[$match[6]] = [$match[6], $name, $gz];
            }
            ksort($list);
            // 检测文件正确性
            $last = end($list);
            if (count($list) === $last[0]) {
                session('backup_list', $list); //缓存备份列表
                return $this->success('初始化完成！', '', [
                    'part' => 1, 
                    'start' => 0,
                ]);
            } else {
                return $this->error('备份文件可能已经损坏，请检查！');
            }
        } elseif (is_numeric($part) && is_numeric($start)) {
            $list = session('backup_list');
            $db = new DatabaseService($list[$part], [
                'path' => realpath(config('app.data_backup_path')) . DIRECTORY_SEPARATOR, 
                'compress' => $list[$part][2],
            ]);
            $start = $db->import($start);
            if (false === $start) {
                return $this->error('还原数据出错！');
            } elseif (0 === $start) {
                //下一卷
                if (isset($list[++$part])) {
                    $data = ['part' => $part, 'start' => 0];
                    return $this->success("正在还原...#{$part}", '', $data);
                } else {
                    session('backup_list', null);
                    return $this->success('还原完成！');
                }
            } else {
                $data = [
                    'part' => $part, 
                    'start' => $start[0],
                ];
                if ($start[1]) {
                    $rate = floor(100 * ($start[0] / $start[1]));
                    return $this->success("正在还原...#{$part} ({$rate}%)", '', $data);
                } else {
                    $data['gz'] = 1;
                    return $this->success("正在还原...#{$part}", '', $data);
                }
            }
        } else {
            return $this->error('参数错误！');
        }
    }

    /**
     * 删除备份文件
     * @param  Integer $time 备份时间
     */
    public function del()
    {
        $time = $id = $this->request->param('time/d');
        if ($time) {
            $name = date('Ymd-His', $time) . '-*.sql*';
            $path = $this->databaseConfig['path'] . $name;
            array_map("unlink", glob($path));
            if (count(glob($path))) {
                return $this->error('备份文件删除失败，请检查权限！');
            } else {
                return $this->success('备份文件删除成功！');
            }
        } else {
            return $this->error('参数错误！');
        }
    }

    /**
     * 优化表
     * @param  String $tables 表名
     */
    public function optimize()
    {
        //表名
        $tables = $this->request->param('tables/a');
        if ($tables) {
            if (is_array($tables)) {
                $tables = implode('`,`', $tables);
                $list = Db::query("OPTIMIZE TABLE `{$tables}`");
                if ($list) {
                    return $this->success("数据表优化完成！");
                } else {
                    return $this->error("数据表优化出错请重试！");
                }
            }
        } else {
            return $this->error("请指定要优化的表！");
        }
    }

    /**
     * 修复表
     * @param  String $tables 表名
     */
    public function repair()
    {
        //表名
        $tables = $this->request->param('tables/a');
        if ($tables) {
            if (is_array($tables)) {
                $tables = implode('`,`', $tables);
                $list = Db::query("REPAIR TABLE `{$tables}`");
                if ($list) {
                    return $this->success("数据表修复完成！");
                } else {
                    return $this->error("数据表修复出错请重试！");
                }
            }
        } else {
            return $this->error("请指定要修复的表！");
        }
    }

}
