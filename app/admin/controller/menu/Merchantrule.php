<?php

namespace app\admin\controller\menu;

use fast\Tree;
use think\facade\Cache;
use app\admin\model\menu\MerchantAuthRule;
use app\common\controller\Backend;

/**
 * 规则管理.
 *
 * @icon   fa fa-list
 * @remark 规则通常对应一个控制器的方法,同时左侧的菜单栏数据也从规则中体现,通常建议通过控制台进行生成规则节点
 */
class Merchantrule extends Backend
{
    /**
     * @var \app\admin\model\menu\MerchantAuthRule;
     */
    protected $model = null;
    protected $rulelist = [];
    protected $multiFields = 'ismenu,status';

    public function _initialize()
    {
        parent::_initialize();
        if (!$this->auth->isSuperAdmin()) {
            $this->error(__('Access is allowed only to the super management group'));
        }
        $this->model = new \app\admin\model\menu\MerchantAuthRule();
        // 必须将结果集转换为数组
        $ruleList = $this->model->order('weigh', 'desc')->order('id', 'asc')->select()->toArray();
        foreach ($ruleList as $k => &$v) {
            $v['title']  = __($v['title']);
            $v['remark'] = __($v['remark']);
        }
        unset($v);
        Tree::instance()->init($ruleList);
        $this->rulelist = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0), 'title');
        $ruledata       = [0 => __('None')];
        foreach ($this->rulelist as $k => &$v) {
            if (!$v['ismenu']) {
                continue;
            }
            $ruledata[$v['id']] = $v['title'];
        }
        unset($v);
        $this->view->assign('ruledata', $ruledata);
    }

    /**
     * 查看.
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $list  = $this->rulelist;
            $total = count($this->rulelist);

            $result = ['total' => $total, 'rows' => $list];

            return json($result);
        }

        return $this->view->fetch();
    }

    /**
     * 添加.
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post('row/a', [], 'strip_tags');
            if ($params) {
                validate(\app\admin\validate\AuthRule::class)->check($params);
                if (!$params['ismenu'] && !$params['pid']) {
                    $this->error(__('The non-menu rule must have parent'));
                }
                $result = $this->model->data($params, true)->save();
                if ($result === false) {
                    $this->error($this->model->getError());
                }
                Cache::delete('__merchant_menu__');
                $this->success();
            }
            $this->error();
        }

        return $this->view->fetch();
    }

    /**
     * 编辑.
     */
    public function edit($ids = null)
    {
        $row = $this->model->find(['id' => $ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post('row/a', [], 'strip_tags');
            if ($params) {
                if (!$params['ismenu'] && !$params['pid']) {
                    $this->error(__('The non-menu rule must have parent'));
                }
                if ($params['pid'] != $row['pid']) {
                    $childrenIds = Tree::instance()->init(MerchantAuthRule::select()->toArray())->getChildrenIds($row['id']);
                    if (in_array($params['pid'], $childrenIds)) {
                        $this->error(__('Can not change the parent to child'));
                    }
                }
                /*//这里需要针对name做唯一验证
                $ruleValidate = validate(\app\admin\validate\AuthRule::class);
                $ruleValidate->rule([
                    'name' => 'require|format|unique:AuthRule,name,'.$row->id,
                ]);
                $ruleValidate->check($params);*/
                $result = $row->data($params, true)->save();
                if ($result === false) {
                    $this->error($row->getError());
                }
                Cache::delete('__merchant_menu__');
                $this->success();
            }
            $this->error();
        }
        $this->view->assign('row', $row);

        return $this->view->fetch();
    }

    /**
     * 删除.
     */
    public function del($ids = '')
    {
        if ($ids) {
            $delIds = [];
            foreach (explode(',', $ids) as $k => $v) {
                $delIds = array_merge($delIds, Tree::instance()->getChildrenIds($v, true));
            }
            $delIds = array_unique($delIds);
            $count  = $this->model->where('id', 'in', $delIds)->delete();
            if ($count) {
                Cache::delete('__merchant_menu__');
                $this->success();
            }
        }
        $this->error();
    }
}
