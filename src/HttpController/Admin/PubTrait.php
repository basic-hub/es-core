<?php


namespace BasicHub\EsCore\HttpController\Admin;

use BasicHub\EsCore\Common\Exception\HttpParamException;
use BasicHub\EsCore\Common\Languages\Dictionary;
use EasySwoole\ORM\AbstractModel;

/**
 * @mixin BaseTrait
 * @property AbstractModel $Model
 */
trait PubTrait
{
    protected function instanceModel()
    {
        $this->Model = model_admin('Admin');
        return true;
    }


    public function index()
    {
        return $this->_login();
    }

    public function _login($return = false)
    {
        $array = $this->post;
        if ( ! isset($array['username'])) {
            throw new HttpParamException(lang(Dictionary::ADMIN_PUBTRAIT_1));
        }

        // 查询记录
        $Admin = $this->Model->where('username', $array['username'])->get();

        if (empty($Admin) || ! password_verify($array['password'], $Admin['password'])) {
            throw new HttpParamException(lang(Dictionary::ADMIN_PUBTRAIT_4));
        }

        // 已被锁定
        if (empty($Admin['status'])) {
            throw new HttpParamException(lang(Dictionary::ADMIN_PUBTRAIT_2));
        }

        /** @var AbstractModel $model */
        $model = model_admin('LogLogin');
        $model->data([
            'uid' => $Admin['id'],
            'name' => $Admin['realname'] ?: $Admin['username'],
            'ip' => ip($this->request()),
        ])->save();

        $result = [
            'token' => get_admin_token($Admin)
        ];
        return $return ? $result + ['data' => $Admin->toArray()] : $this->success($result, Dictionary::ADMIN_PUBTRAIT_3);
    }

    public function logout()
    {
        $this->success('success');
    }
}
