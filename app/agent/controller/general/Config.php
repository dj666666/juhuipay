<?php
/**
 * *
 *  * ============================================================================
 *  * Created by PhpStorm.
 *  * User: Ice
 *  * 邮箱: ice@sbing.vip
 *  * 网址: https://sbing.vip
 *  * Date: 2019/9/19 下午5:05
 *  * ============================================================================.
 */

namespace app\agent\controller\general;

use think\Exception;
use think\facade\Validate;
use app\common\library\Email;
use app\common\controller\AgentBackend;
use app\common\model\Config as ConfigModel;

/**
 * 系统配置.
 *
 * @icon fa fa-cogs
 * @remark 可以在此增改系统的变量和分组,也可以自定义分组和变量,如果需要删除请从数据库中删除
 */
class Config extends AgentBackend
{
    /**
     * @var \app\common\model\Config
     */
    protected $model = null;
    protected $noNeedRight = ['check', 'rulelist'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new ConfigModel();
    }

    /**
     * 查看.
     */
    public function index()
    {
        $siteList = [];
        $groupList = ConfigModel::getGroupList();
        foreach ($groupList as $k => $v) {
            $siteList[$k]['name'] = $k;
            $siteList[$k]['title'] = $v;
            $siteList[$k]['list'] = [];
        }
        foreach ($this->model->select() as $k => $v) {
            if (! isset($siteList[$v['group']])) {
                continue;
            }
            $value = $v->toArray();
            $value['title'] = __($value['title']);
            if (in_array($value['type'], ['select', 'selects', 'checkbox', 'radio'])) {
                $value['value'] = explode(',', $value['value']);
            }
            $value['content'] = json_decode($value['content'], true);
            $value['tip'] = htmlspecialchars($value['tip']);
            $siteList[$v['group']]['list'][] = $value;
        }
        $index = 0;
        foreach ($siteList as $k => &$v) {
            $v['active'] = ! $index ? true : false;
            $index++;
        }
        $this->view->assign('siteList', $siteList);
        $this->view->assign('typeList', ConfigModel::getTypeList());
        $this->view->assign('ruleList', ConfigModel::getRegexList());
        $this->view->assign('groupList', ConfigModel::getGroupList());

        return $this->view->fetch();
    }

    /**
     * 添加.
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a", [], 'trim');
            if ($params) {
                foreach ($params as $k => &$v) {
                    $v = is_array($v) ? implode(',', $v) : $v;
                }

                try {
                    if (in_array($params['type'], ['select', 'selects', 'checkbox', 'radio', 'array'])) {
                        $params['content'] = json_encode(ConfigModel::decode($params['content']),
                            JSON_UNESCAPED_UNICODE);
                    } else {
                        $params['content'] = '';
                    }
                    $result = $this->model->create($params);
                    if ($result !== false) {
                        try {
                            $this->refreshFile();
                        } catch (Exception $e) {
                            $this->error($e->getMessage());
                        }
                        $this->success();
                    } else {
                        $this->error($this->model->getError());
                    }
                } catch (Exception $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        return $this->view->fetch();
    }

    /**
     * 编辑.
     *
     * @param null $ids
     */
    public function edit($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
            $row = $this->request->post("row/a", [], 'trim');
            if ($row) {
                $configList = [];
                foreach ($this->model->select() as $v) {
                    if (isset($row[$v['name']])) {
                        $value = $row[$v['name']];
                        if (is_array($value) && isset($value['field'])) {
                            $value = json_encode(ConfigModel::getArrayData($value), JSON_UNESCAPED_UNICODE);
                        } else {
                            $value = is_array($value) ? implode(',', $value) : $value;
                        }
                        $v['value'] = $value;
                        $configList[] = $v->toArray();
                    }
                }
                $this->model->saveAll($configList);

                try {
                    $this->refreshFile();
                } catch (Exception $e) {
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
    }

    public function del($ids = '')
    {
        $name = $this->request->post('name');
        $config = ConfigModel::getByName($name);
        if ($name && $config) {
            try {
                $config->delete();
                $this->refreshFile();
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
            $this->success();
        } else {
            $this->error(__('Invalid parameters'));
        }
    }

    /**
     * 刷新配置文件.
     */
    protected function refreshFile()
    {
        $config = [];
        foreach ($this->model->select() as $k => $v) {
            $value = $v->toArray();
            if (in_array($value['type'], ['selects', 'checkbox', 'images', 'files'])) {
                $value['value'] = explode(',', $value['value']);
            }
            if ($value['type'] == 'array') {
                $value['value'] = (array) json_decode($value['value'], true);
            }
            $config[$value['name']] = $value['value'];
        }
        file_put_contents(app()->getConfigPath().'site.php',
            '<?php'."\n\nreturn ".varexport($config, true).';');
    }

    /**
     * 检测配置项是否存在.
     *
     * @internal
     */
    public function check()
    {
        $params = $this->request->post('row/a');
        if ($params) {
            $config = $this->model->find($params);
            if (! $config) {
                return $this->success();
            } else {
                return $this->error(__('Name already exist'));
            }
        } else {
            return $this->error(__('Invalid parameters'));
        }
    }

    /**
     * 规则列表
     * @internal
     */
    public function rulelist()
    {
        //主键
        $primarykey = $this->request->request("keyField");
        //主键值
        $keyValue = $this->request->request("keyValue", "");

        $keyValueArr = array_filter(explode(',', $keyValue));
        $regexList = \app\common\model\Config::getRegexList();
        $list = [];
        foreach ($regexList as $k => $v) {
            if ($keyValueArr) {
                if (in_array($k, $keyValueArr)) {
                    $list[] = ['id' => $k, 'name' => $v];
                }
            } else {
                $list[] = ['id' => $k, 'name' => $v];
            }
        }
        return json(['list' => $list]);
    }

    /**
     * 发送测试邮件.
     *
     * @internal
     */
    public function emailtest()
    {
        $row = $this->request->post('row/a');
        $receiver = $this->request->post("receiver");
        if ($receiver) {
            if (!Validate::is($receiver, "email")) {
                $this->error(__('Please input correct email'));
            }
            \think\facade\Config::set(array_merge(\think\facade\Config::get('site'), $row), 'site');
            $email = new Email;
            $result = $email
                ->to($receiver)
                ->subject(__("This is a test mail"))
                ->message('<div style="min-height:550px; padding: 100px 55px 200px;">' . __('This is a test mail content') . '</div>')
                ->send();
            if ($result) {
                $this->success();
            } else {
                $this->error($email->getError());
            }
        } else {
            $this->error(__('Invalid parameters'));
        }
    }
}
