<?php

namespace BasicHub\EsCore\Notify\Feishu\Message;

class Textarea extends Base
{
    public function fullData()
    {
        if (is_array($this->content))
        {
            $content = [];
            foreach ($this->content as $item)
            {
                $content[] = [
                    'tag' => 'text',
                    'text' => $item,
                ];
            }
            $content[] = [
                'tag' => 'text',
                'text' => $this->getServerText(),
            ];
        }else{
            $content = [
                [
                    'tag' => 'text',
                    'text' => $this->inner ? ($this->content . $this->getServerText()) : $this->content,
                ],
            ];
        }

        $data = [
            'msg_type' => 'post',
            'content' => [
                'post' => [
                    'zh_cn' => [
                        'title' => $this->title,
                        'content' => [
                           $content,
                        ],
                    ],
                ],
            ],
        ];
        $at = $this->getAtTpl();
        $data['content']['post']['zh_cn']['content'][0]  = array_merge($data['content']['post']['zh_cn']['content'][0], $at);
        return $data;
    }
}
