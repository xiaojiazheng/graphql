<?php

namespace Xiaobe\graphql\root;

use support\Request;
use Xiaobe\graphql\exception\HookSetException;
use Xiaobe\graphql\exception\queryrunning\QueryParseException;
use Xiaobe\graphql\exception\queryaccess\QueryNotAllowException;


/**
 * 图表查询服务
 * 文档
 * 1.const prop = { prop 指定需要返回的字段，除了聚合函数字段以外，其他地方使用的字段都必须在这里写出
 *           maker: '', 为空''不筛选
 *           created_at: ['~', start, end], 表示'~'范围筛选
 *           order_type: orderType != '全部' ? orderType : '',  表示筛选或者不筛选
 *           is_red: 1  数字筛选
 *           materialcollectionMaterial: {  执行嵌套关联查询，
 *              id: '', 注意，在模型里关联的字段必须在需要返回的字段里，且分组的时候也不可缺少，否则会返回null
 *              material_code: '',  关联查询同样可以进行筛选，但不会影响主查询
 *              materialcollection: { 深度关联查询更加注意，主和属模型关联的字段一定要列出来，否则必定会为null查不到
 *                 id: '',
 *                 name: ''
 *              }
 *          }
 *          material_code
 * ?模糊匹配  !非匹配 支持如 ?3&?5 (?3|?5) 模糊匹配3和(或)5的写法 
 *         }
 * 2.const QL = [ 为执行多次重复模型查询,用对象包裹放在数组
 *           {
 *             bill_receive: { QL图表名 自己配置映射
 *               sum: { 求和 内容为空也会返回count计数
 *                 order: ['maker'],  以maker分组，已经优化过了，分组的字段可以比总返回字段少
 *                 ori_net_quantity: '',  指定列求和，不指定默认使用count计数
 *                 confirm_net_quantity: '' 指定列求和
 *               },
 *               prop 见1
 *             }
 *           },
 *           {
 *             bill_receive: { QL图表名 可重复
 *               sum: {
 *                 order: ['is_red', 'order_type'] 会返回count计数，不指定sum字段
 *               },
 *               prop 见1
 *             }
 *           }
 *         ]
 */
abstract class GraphQLService
{
  protected QueryModel $model;
  protected ModelMapper $modelMapper;
  protected string $action = '';
  protected array $disabledQuery = [];
  protected array $disabledMutation = [];
  protected array $query = [];
  protected array $mutation = [];

  public function __construct()
  {
    $this->modelMapper = new ModelMapper();
  }
  public function index(Request $request)
  {
    $this->GraphQLParse($request);
    if ($this->action == '')
      return json_encode(['data' => [], 'msg' => 'GraphQL图表服务启动成功', 'code' => 0], JSON_UNESCAPED_UNICODE);
    $this->setMapping();
    $this->CheckDisabled($this->action);
    return $this->action === 'query' ? $this->executeQuery() :  $this->executeMutation();
  }
  /**
   * 图表解析
   * 获取参数
   */
  protected function GraphQLParse(Request $request)
  {
    $query = $request->get('query');
    $mutation = $request->get('mutation');

    if ($query) {
      $this->query = json_decode($query, true);
      $this->action = 'query';
    } else if ($mutation) {
      $this->mutation = json_decode($mutation, true);
      $this->action = 'mutation';
    }
  }
  protected function setDisabled()
  {
  }
  /**
   * $qlName 图表名
   * $propKeys prop里字段名，为索引数组
   */
  protected function addQueryDisabled(string $qlName, array $propKeys)
  {
    $this->disabledQuery[$qlName] = $propKeys;
  }
  /**
   * $qlName 图表名
   * $propKeys prop里字段名，为索引数组
   */
  protected function addMutationDisabled(string $qlName, array $propKeys)
  {
    $this->disabledMutation[$qlName] = $propKeys;
  }
  /**
   * 检查参数
   * 限制字段
   */
  protected function CheckDisabled($action)
  {
    $disablededKeys = [];

    // 按照不同的操作进行设置允许的键
    $disablededKeys = $action === 'query' ? $this->disabledQuery : $this->disabledMutation;
    if (empty($disablededKeys)) {
      return;
    }

    $fields = $action === 'query' ? $this->query : $this->mutation;
    if (!is_array($fields)) {
      throw new QueryParseException('从query或mutation解析参数失败', 1);
    }

    foreach ($fields as  $query) {
      $qlName = array_keys($query)[0];
      $args = $query[$qlName];
      if (!isset($disablededKeys[$qlName]))
        continue;
      $disallowedKeys = array_intersect(array_keys($args['prop']), $disablededKeys[$qlName]);

      if (!empty($disallowedKeys)) {
        throw new QueryNotAllowException("图表{$qlName}的prop字段中,属性[" . implode(', ', $disallowedKeys) . "]被禁止使用", 1);
      }
    }
  }
  /**
   * 配置映射
   */
  abstract protected function setMapping();

  protected function executeQuery()
  {
    $result = [];
    foreach ($this->query as $query) {
      $qlName = array_keys($query)[0];
      $args = $query[$qlName];
      $this->model = $this->modelMapper->getModel($qlName);
      $QLbody = [
        'addition' => [],
        'properties' => [],
        'sum' => [],
      ];
      if (isset($args['addition'])) {
        $QLbody['addition'] = $args['addition'];
      }
      if (isset($args['sum'])) {
        $QLbody['sum'] = $args['sum'];
      }
      if (isset($args['prop'])) {
        $QLbody['properties'] = $args['prop'];
      }
      $result[] = [$qlName => $this->model->executeQuery($QLbody)];
    }
    // 返回结果
    return json_encode(['data' => $result, 'msg' => '查询成功', 'code' => 0], JSON_UNESCAPED_UNICODE);
  }

  protected function executeMutation()
  {
    throw new HookSetException();
  }
}
