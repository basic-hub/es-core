<?php


namespace BasicHub\EsCore\HttpController\Admin;

use BasicHub\EsCore\Common\Exception\HttpParamException;
use BasicHub\EsCore\Common\Languages\Dictionary;
use EasySwoole\Http\AbstractInterface\Controller;

/**
 * @mixin Controller
 * @property \App\Model\Admin\Admin $Model
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
        $data = $this->Model->where('username', $array['username'])->get();

        if (empty($data) || ! password_verify($array['password'], $data['password'])) {
            throw new HttpParamException(lang(Dictionary::ADMIN_PUBTRAIT_4));
        }

        $data = $data->toArray();

        // 已被锁定
        if (empty($data['status'])) {
            throw new HttpParamException(lang(Dictionary::ADMIN_PUBTRAIT_2));
        }

        /** @var \App\Model\Admin\LogLogin $model */
        $model = model_admin('LogLogin');
        $model->data([
            'uid' => $data['id'],
            'name' => $data['realname'] ?: $data['username'],
            'ip' => ip($this->request()),
        ])->save();

        $result = [
            'token' => $this->getLoginToken($data['id'])
        ];
        return $return ? $result + ['data' => $data] : $this->success($result, Dictionary::ADMIN_PUBTRAIT_3);
    }

    /**
     * 后台登录token
     * @param $data
     * @return string
     */
    protected function getLoginToken($data)
    {
        return get_token(['id' => $data['id']]);
    }

    public function logout()
    {
        $this->success('success');
    }
}
