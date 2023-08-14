<?php

namespace xiaobe\Graphql\root;

use support\Request;
use app\admin\controller\Curd;
use Xiaobe\Graphql\exception\HookSetException;
use xiaobe\Graphql\exception\RequestTypeException;
use xiaobe\Graphql\exception\RestfulTypeException;

/**
 * @author xiaojiazheng <16673800623>
 * @category: Curd
 * @email: 2162621686@qq.com
 * @date: 2023-7-20 08:28
 * @description
 * RestFul接口
 */
class RestFulApi extends Curd
{
  /**
   * @var array
   * 可钩列表
   */
  private array $hooks = [
    'get' => '',
    'post' => '',
    'patch' => '',
    'delete' => ''
  ];
  /**
   * @var array
   * 钩子函数映射配置式配置
   * 严格按顺序
   * 优先级小于setxxxHook
   */
  protected array $reflect = ['Query', 'Add', 'Edit', 'Del'];
  /**
   * @var string
   * web服务方法
   * 启用则直接返回view
   */
  protected string $webservice = '';

  public function __construct()
  {
    $funcPool = ['setGetHook', 'setPostHook', 'setPATCHHook', 'setDeleteHook'];
    foreach ($this->reflect as $value) {
      if (method_exists($this, $value)) {
        $set = current($funcPool);
        $this->$set($value);
      }
      next($funcPool);
    }
    reset($funcPool);
  }


  protected function setGetHook(string $hook)
  {
    $this->hooks['get'] = $hook;
  }
  protected function setPostHook(string $hook)
  {
    $this->hooks['post'] = $hook;
  }
  protected function setPATCHHook(string $hook)
  {
    $this->hooks['patch'] = $hook;
  }
  protected function setDeleteHook(string $hook)
  {
    $this->hooks['delete'] = $hook;
  }
  protected function getHook(string $hookName)
  {
    if ($this->hooks[$hookName] == '') {
      throw new HookSetException();
    } else {
      return $this->hooks[$hookName];
    }
  }

  public function index(Request $request, ...$args)
  {
    if ($request->isAjax() || $this->webservice) {
      // 如果是 Ajax 请求，则执行相应的逻辑
      $methodName = '';
      switch ($request->method()) {
        case 'GET':
          // 处理 GET 请求的逻辑
          if (!$request->isAjax() && $this->webservice)
            $methodName = $this->webservice;
          else
            $methodName = $this->getHook('get');
          break;
        case 'POST':
          // 处理 POST 请求的逻辑
          $methodName = $this->getHook('post');
          break;
        case 'PATCH':
          // 处理 PATCH 请求的逻辑s
          $methodName = $this->getHook('patch');
          break;
        case 'DELETE':
          // 处理 DELETE 请求的逻辑
          $methodName = $this->getHook('delete');
          break;
        default:
          throw new RestfulTypeException($request->method());
      }
      return $this->$methodName($request, ...$args);
    } else {
      throw new RequestTypeException();
    }
  }

  protected function gotSuccess($data, $msg = '操作成功', $code = 0)
  {
    return $this->success(dataReturn($code, $msg, $data));
  }
  protected function gotFault($msg = '出现错误', $code = -1)
  {
    return $this->success(dataReturn($code, $msg));
  }
  protected function gotInfo($msg = '提示模板', $code = 0)
  {
    return $this->success(dataReturn($code, $msg));
  }
}
