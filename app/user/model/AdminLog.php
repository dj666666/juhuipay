<?php

namespace app\user\model;

use app\user\library\Auth;
use app\common\model\BaseModel;

class AdminLog extends BaseModel
{
    protected $name = 'user_log';

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = '';
    //自定义日志标题
    protected static $title = '';
    //自定义日志内容
    protected static $content = '';

    public static function setTitle($title)
    {
        self::$title = $title;
    }

    public static function setContent($content)
    {
        self::$content = $content;
    }

    public static function record($title = '')
    {
        $auth = Auth::instance();
        $admin_id = $auth->isLogin() ? $auth->id : 0;
        $username = $auth->isLogin() ? $auth->username : __('Unknown');
        $content = self::$content;
        if (! $content) {
            $content = request()->param('', null, 'trim,strip_tags,htmlspecialchars');
            foreach ($content as $k => $v) {
                //if (is_string($v) && strlen($v) > 200 || stripos($k, 'password') !== false) {
                if (is_string($v) && strlen($v) > 200) {
                    unset($content[$k]);
                }
            }
        }
        $title = self::$title;
        if (! $title) {
            $title = [];
            $breadcrumb = Auth::instance()->getBreadcrumb();
            foreach ($breadcrumb as $k => $v) {
                $title[] = $v['title'];
            }
            $title = implode(' ', $title);
        }
        self::create([
            'title'     => $title,
            'content'   => ! is_scalar($content) ? json_encode($content) : $content,
            'url'       => substr(request()->url(), 0, 1500),
            'admin_id'  => $admin_id,
            'username'  => $username,
            'useragent' => substr(request()->server('HTTP_USER_AGENT'), 0, 255),
            'ip'        => request()->ip(),
        ]);
    }

    public function admin()
    {
        return $this->belongsTo('Admin', 'admin_id');
    }
}
