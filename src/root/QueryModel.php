<?php

namespace Xiaobe\Graphql\root;

use support\Model;
use Xiaobe\Graphql\exception\HookSetException;
use Xiaobe\Graphql\exception\queryrunning\ModelRunningException;

/**
 * @author xiaojiazheng <16673800623>
 * @email: 2162621686@qq.com
 * @date: 2023-7-21 20:08
 * @description
 * 查询模型
 * 自动化处理查询请求
 */
class QueryModel extends Model
{
    /**
     * 查询单例
     */
    protected $queryInstance = null;
    /**.
     * 关闭时间戳维护
     */
    public $timestamps = false;

    /**
     * 执行查询
     * 实现
     * 1.where 多条件 与或 模糊 精确 范围 包含
     * 2.with 关联查询 递归关联查询 关联查询挑选列
     * 2.order 根据主查询分组 求和
     */
    public function executeQuery($QLbody)
    {
        $query = $this->getqueryInstance();
        $this->withWhere($query, $QLbody['properties']);
        $this->withSum($query, $QLbody);
        return $this->gotSuccess($query, $QLbody);
    }
    /**
     * 执行变更
     * 没有实现一点功能
     */
    public function executeMutation($QLbody)
    {
        throw new HookSetException();
    }
    /**
     * 获取查询单例
     */
    public function getqueryInstance()
    {
        if (!$this->queryInstance)
            $this->queryInstance = $this->query();
        return $this->queryInstance;
    }
    /**
     * 实现自动化查询功能
     * 现在可以处理关联查询了
     */
    protected function withWhere($query, &$prop, $withmode = false)
    {
        $withKey = array_keys($prop);
        foreach ($prop as $keyword => $value) {
            if (!$value && $value != 0) {
                continue;
            }
            if (is_numeric($value)) {
                $this->applyCondition($query, $keyword, $value);
            } else if (is_string($value)) {
                // 兼容条件与或操作
                $conditions = preg_split('/([&|])/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
                $conditions = array_map('trim', $conditions);
                $conditions = array_filter($conditions, function ($condition) {
                    return !empty($condition); // 过滤掉空字符串
                });
                $whereMethod = 'where';
                foreach ($conditions as $condition) {
                    if (in_array($condition, ['&', '|'])) {
                        $conditions == '&' ? $whereMethod = 'where' : $whereMethod = 'orWhere';
                        continue;
                    }
                    $this->applyCondition($query, $keyword, $condition, $whereMethod);
                }
            } else if (is_array($value)) {
                if (array_keys($value) === range(0, count($value) - 1)) {
                    // 判断数组第一个元素是否为"~"
                    if ($value[0] === '~') {
                        // 使用whereBetween进行条件筛选
                        if (count($value) >= 3) {
                            $minValue = !empty($value[1]) ? $value[1] : null;
                            $maxValue = !empty($value[2]) ? $value[2] : null;
                            if ($minValue !== null || $maxValue !== null) {
                                if ($minValue !== null) {
                                    $query->where($keyword, '>=', $minValue);
                                }
                                if ($maxValue !== null) {
                                    $query->where($keyword, '<=', $maxValue);
                                }
                            }
                        } else {
                            throw new ModelRunningException("存在名为{$keyword}的图表查询模板prop值不符合whereBetween条件筛选的要求", 2);
                        }
                    } else {
                        // 使用whereIn进行条件筛选
                        $query->whereIn($keyword, $value);
                    }
                } else {
                    if ($withmode) {
                        $withKey = array_diff($withKey, [$keyword]);
                    }
                    // 嵌套关联查询
                    $query->with([$keyword => function ($query) use ($value) {
                        $this->withWhere($query, $value, true);
                    }]);
                    unset($prop[$keyword]);
                }
            } else {
                throw new ModelRunningException("存在名为{$keyword}的图表查询模板prop值不是字符串或数组类型", 1);
            }
        }
        if ($withmode) {
            $query->select($withKey);
        }
    }

    /**
     * 自动化查询辅助函数
     * 如果是值字符串类型
     * 实现多条件查询
     */
    protected function applyCondition($query, string $keyword, string $value, string $whereMethod = 'where')
    {
        if ($value[0] === '?') {
            // 模糊匹配
            $searchValue = substr($value, 1);
            $query->$whereMethod($keyword, 'like', '%' . $searchValue . '%');
        } elseif ($value[0] === '!') {
            // 不等于查询
            $notEqualValue = substr($value, 1);
            $query->$whereMethod($keyword, '<>', $notEqualValue);
        } else {
            // 等于查询
            $query->$whereMethod($keyword, '=', $value);
        }
    }

    /**
     * 实现总和功能
     * 只能放在最后一个钩子
     * 由于需要修改QLbody体properties字段
     */
    protected function withSum($query, &$QLbody)
    {
        if (isset($QLbody['sum'])) {
            if (isset($QLbody['sum']['order']) && $QLbody['sum']['order']) {
                $sonOrder = [];
                $order = array_filter($QLbody['sum']['order'], function ($value) use (&$sonOrder) {
                    $result = strpos($value, '.') === false;
                    if (!$result)
                        $sonOrder[] = $value;
                    return $result;
                });
                unset($QLbody['sum']['order']);
                if ($sonOrder)
                    $QLbody['addition']['groupBy'] = $sonOrder;
                if (!$order) {
                    return;
                }
                $QLbody['properties'] = array_intersect_key($QLbody['properties'], array_flip($order));
                $columns = array_keys($QLbody['properties']);
                $query->select($columns)->groupBy($columns);
            }
            $query->selectRaw('COUNT(*) as total_count');
            $sumFields = array_keys($QLbody['sum']);
            if ($sumFields) {
                foreach ($sumFields as $field) {
                    $alias = "sum_{$field}";
                    $query->selectRaw("SUM({$field}) as {$alias}");
                }
            }
        }
    }

    protected function gotSuccess($query, $QLbody)
    {
        $addition = $QLbody['addition'];
        $columns =  array_keys($QLbody['properties']);
        $limit = $addition['limit'] ?? 50;
        $page = $addition['page'] ?? 1;
        $orderbys = $addition['orderby'];
        foreach ($orderbys as $field => $direction) {
            $query->orderBy($field, $direction > 0 ? 'asc' : 'desc');
        }
        if (isset($addition['groupBy'])) {
            $needSum = array_keys($QLbody['sum']);
            $collection = $query->get(array_merge($columns, $needSum));
            $groupedCollection = $collection->groupBy(function ($item) use ($addition) {
                $itemArray = toArray($item);
                $return = '';
                foreach ($addition['groupBy'] as $oneBy) {
                    $by = explode('.', $oneBy);
                    $value = $itemArray;
                    foreach ($by as $key) {
                        $key = preg_replace('/(?<!^)([A-Z])/', '_$1', $key);
                        $key = strtolower($key);
                        if (isset($value[$key])) {
                            $value = $value[$key];
                        } else {
                            $value = '未分类';
                            break;
                        }
                    }
                    $return .= $value;
                }
                return $return;
            });
            $returnArray = [];
            foreach ($groupedCollection as $key => $value) {
                $groupSum = [];

                $groupSum['total_count'] = 0;

                if (!is_array($value)) $value = toArray($value);

                foreach ($needSum as $field) {
                    $groupSum['sum_' . $field] = 0;
                }
                array_map(function ($item) use ($needSum, &$groupSum) {
                    foreach ($needSum as $field) {
                        $groupSum['sum_' . $field] += $item[$field] ?? $item['sum_' . $field];
                    }
                    isset($item['total_count']) ? $groupSum['total_count'] += $item['total_count'] : '';
                }, $value);
                $groupSum['total_count'] == 0 ? $groupSum['total_count'] = count($value) : '';
                $groupSum['group'] = $key; // 将分组的字段总和附加到分组集合中

                // 将分组的字段总和保存到 $sum 数组中
                foreach ($groupSum as &$one) {
                    foreach ($groupSum as &$one) {
                        if (!is_numeric($one))
                            continue;
                        $one = round(floatval($one), 8);
                    }
                }
                $returnArray[] = $groupSum;
            }
            if (isset($addition['limit'])) {
                $start = ($page - 1) * $limit;
                $total = count($returnArray);

                if ($start >= $total) {
                    return ['data' => [], 'total' => $total];
                }

                $end = min($start + $limit, $total);
                $pagedData = array_slice($returnArray, $start, $end - $start);

                return ['data' => $pagedData, 'total' => $total];
            } else
                return $returnArray;
            foreach ($groupedCollection as $key => $value) {
                $groupedCollectionArray[] = ['group' => $key, 'value' => $value->toArray()];
            }
            return $groupedCollectionArray;
        }
        if (isset($addition['limit']))
            return $query->paginate($limit, $columns, 'page', $page);
        else
            return $query->take(100)->get($columns)->toArray();
    }
}
