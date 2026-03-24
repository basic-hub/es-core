<?php

namespace BasicHub\EsCore\Common\OrmCache;

use BasicHub\EsCore\Model\BaseModelTrait;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;

/**
 * 字符串缓存，适用于单行缓存
 *
 * 2026-03-24 移除bloom逻辑：
 * ┌────────────────────────────┬──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┐
 * │            问题            │                                                         说明                                                          │
 * ├────────────────────────────┼──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
 * │ 不是真正的 Bloom Filter     │ Redis Set 是精确集合，Bloom Filter 是概率结构（允许误判）。叫法错误会误导维护者                                             │
 * ├────────────────────────────┼──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
 * │ 内存随数据量线性增长         │ Set 每个元素存完整值，百万行表就是百万个 string，内存开销大，注释里也写了"大表谨慎开启"                                        │
 * ├────────────────────────────┼──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
 * │ 数据变更时整个集合删除重建    │ cacheDel / _after_cache 里直接 bloomDel()，下次查询再全量重建，重建期间所有请求都穿透到 DB                                  │
 * ├────────────────────────────┼──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
 * │ 解决的问题已有更简单的方案    │ 它的目的是防缓存穿透（查不存在的 id），但代码里已经有 penetrate 防穿透标识（空结果也缓存 PENETRATION），两套机制重叠            │
 * └────────────────────────────┴──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┘
 * penetrate 已经够用：
 * 当前代码查不到数据时会缓存 PENETRATION 字符串，下次同一个 id 查询直接返回 false，不走 DB。这已经完整解决了缓存穿透问题。
 *
 * bloom 集合额外解决的场景是：从未被查询过的不存在 id，在第一次查询时拦截，避免打到 DB。但这个收益要对比成本：
 * - 集合本身需要全量加载一次 DB 数据
 * - 数据变更就要重建
 * - 重建期间反而有击穿风险
 *
 * 只有在**恶意刷不存在 id（爬虫/攻击）**的场景下，bloom 才有明显价值，因为 penetrate 对每个不同的 id 都要先打一次 DB 才能缓存空值。
 *
 * 建议：
 *
 * 1. 普通业务：直接移除 bloom，penetrate 已经足够
 * 2. 有恶意请求风险：在网关/业务层做 id 合法性校验（如 id 必须是正整数且在合理范围内），比维护一个全量集合更轻量
 * 3. 真的需要 bloom：使用 Redis 官方的 RedisBloom 模块（BF.ADD / BF.EXISTS），它是真正的概率结构，内存固定，不随数据量线性增长，且支持原子操作
 *
 * @mixin AbstractModel
 * @mixin BaseModelTrait
 */
trait Strings
{
    use Events;

    /**
     * 有效期, s
     * @var float|int
     */
    protected $expire = 7 * 86400;

    /**
     * 是否要生成防穿透标识
     * @var string
     */
    protected $penetrate = true;

    /**
     * 防穿透标识
     * @var string
     */
    private $penetrationSb = 'PENETRATION';

    /**
     * 连接池
     * @var string
     */
    protected $redisPoolName = 'default';

    /**
     * 前缀，null则获取数据表名
     * @var null
     */
    protected $prefix = null;

    /**
     * 将数组key进行md5
     * @var bool
     */
    protected $isMd5 = true;

    /**
     * 将extension数据合并到主数据
     * @var bool
     */
    protected $mergeExt = false;

    /**
     * key连接符
     * @var string
     */
    protected $joinSb = '-';

    /**
     * 主键标识
     * @var string
     */
    private $primarySb = 'QUOTE:';

    /**
     * 懒惰模式，数据发生变化时，仅删除缓存key，不主动set缓存
     * @var bool
     */
    protected $lazy = true;

    /**
     * 延迟双删开关
     * 方案1：定时器实现，高并发场景下，可能短时间内创建大量定时器任务，导致内存溢出和阻塞eventLoop定时器调度风险。
     * 代码实现：
     * protected function delayDelete($id)
     * {
     *      Timer::getInstance()->after($this->delayTime, function () use ($id) {
     *          $this->cacheDel($id, false);
     *      });
     * }
     * 方案2：zSet实现延时任务，需独立监听redis队列。不考虑，组件不应该限制/强制业务层进程启动
     * 鉴于目前并没有强制需求，暂不实现延迟双删
     * @var bool
     */
    protected $delay = false;

    /**
     * 延迟双删时间（毫秒），建议根据业务QPS调整，默认500ms
     * @var int
     */
    protected $delayTime = 500;

    public function getCacheKey($id)
    {
        if (is_array($id)) {
            ksort($id);
            $str = urldecode(http_build_query($id));
            $id = $this->isMd5 ? md5($str) : $str;
        }
        $prefix = is_null($this->prefix) ? $this->getTableName() : $this->prefix;
        return $prefix . $this->joinSb . $id;
    }

    /**
     * 从数据表查询记录。注意此方法可能在具体模型里进行重写！
     * @param string|array $id 主键值 | ['key' => 'value']
     * @return array
     */
    protected function _getByUnique($id)
    {
        /** @var AbstractModel $data */
        $data = $this->get($id);
        return $data ? $data->toArray() : [];
    }

    protected function _mergeExt($data)
    {
        if (isset($data['extension']) && is_array($data['extension'])) {
            $data += $data['extension'];
            unset($data['extension']);
        }
        return $data;
    }

    /**
     * 从 $data 中提取主键值，单主键返回标量，联合主键返回数组，无主键或字段缺失返回 null
     */
    protected function _getPkValue(array $data)
    {
        $pk = $this->getPk();
        if ($pk === null) {
            return null;
        }
        if (is_array($pk)) {
            $pkValue = [];
            foreach ($pk as $field) {
                if ( ! isset($data[$field])) {
                    return null;
                }
                $pkValue[$field] = $data[$field];
            }
            return $pkValue;
        }
        return $data[$pk] ?? null;
    }

    /**
     * @param string|array $id 主键值 | ['key' => 'value']
     * @param array $data
     * @return mixed|null
     */
    public function cacheSet($id, $data = [])
    {
        // $id 是条件数组（非主键数组）时，额外用主键缓存一份，原key存指针
        // 提到 invoke 外部避免连接池嵌套
        $pkValue = is_array($id) && is_array($data) ? $this->_getPkValue($data) : null;
        if ($pkValue !== null && $id !== $pkValue) {
            $this->cacheSet($pkValue, $data);
            $data = $this->primarySb . json_encode($pkValue, JSON_UNESCAPED_UNICODE);
        }

        return RedisPool::invoke(function (Redis $redis) use ($id, $data) {
            $key = $this->getCacheKey($id);

            is_array($data) && $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            // 防雪崩偏移,偏移量为有效期的1/20
            $offset = intval($this->expire / 20);

            mt_srand();
            $expire = mt_rand($this->expire - $offset, $this->expire + $offset);
            return $redis->setEx($key, $expire, $data);
        }, $this->redisPoolName);
    }

    public function cacheGet($id, $mergeExt = null)
    {
        // 指针解析提到 invoke 外部，避免连接池嵌套
        $rawData = RedisPool::invoke(function (Redis $redis) use ($id) {
            return $redis->get($this->getCacheKey($id));
        }, $this->redisPoolName);

        if ($this->penetrate && $rawData === $this->penetrationSb) {
            return false;
        }

        // 存储的是主键指针，解析出真实主键后重新走完整 cacheGet 流程
        if (is_string($rawData) && strpos($rawData, $this->primarySb) === 0) {
            $pkValue = json_decode(substr($rawData, strlen($this->primarySb)), true);
            if ($pkValue !== null) {
                return $this->cacheGet($pkValue, $mergeExt);
            }
            // 指针损坏，删除脏数据，后续走 DB 重建
            $this->cacheDel($id);
            $rawData = null;
        }

        $data = $rawData;

        // 没有数据，从数据表获取
        if (is_null($data) || $data === false) {
            $data = $this->_getByUnique($id);
            if ( ! $data && $this->penetrate) {
                $data = $this->penetrationSb;
            }
            $data && $this->cacheSet($id, $data);
        }

        if (is_string($data)) {
            $data = json_decode_ext($data);
        }
        $isMerge = is_null($mergeExt) ? $this->mergeExt : $mergeExt;
        if ($isMerge) {
            $data = $this->_mergeExt($data);
        }
        return $data === $this->penetrationSb ? false : $data;
    }

    public function cacheDel($id)
    {
        return RedisPool::invoke(function (Redis $redis) use ($id) {
            $key = $this->getCacheKey($id);
            return $redis->del($key);
        }, $this->redisPoolName);
    }


    /*-------------------------- 模型事件 --------------------------*/

    protected function _after_cache()
    {
        $data = $this->toArray();
        $pkValue = $this->_getPkValue($data);

        // 单主键新增时可能没有id，尝试从lastInsertId补充
        if ($pkValue === null) {
            $pk = $this->getPk();
            if ( ! is_array($pk) && $pk !== null) {
                $insertId = $this->lastQueryResult()->getLastInsertId();
                if ($insertId) {
                    $data[$pk] = $insertId;
                    $pkValue = $insertId;
                }
            }
        }

        if ($pkValue !== null) {
            $this->lazy ? $this->cacheDel($pkValue) : $this->cacheSet($pkValue, $data);
        }
    }
}
