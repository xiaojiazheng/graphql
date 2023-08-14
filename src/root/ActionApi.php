<?php

namespace xiaobe\Graphql\root;

use support\Request;
use support\Response;
use Xiaobe\Graphql\exception\MutationCheckException;

/**
 * 变更 控制器 抽象类
 */
abstract class ActionApi
{
	protected array $reflectRecords;

	public function __construct()
	{
		$this->reflectRecords = [];
		$this->init();
	}
	/**
	 * 控制器初始化
	 */
	abstract public function init();
	/**
	 * 为一个变更设置一个处理函数
	 * @param string $query 查询参数
	 * @param string $service 服务类的完全限定名称
	 */
	protected function addRecord(string $query, $handler)
	{
		$this->reflectRecords[$query] = $handler;
	}
	public function index(Request $request)
	{
		$mutation = $request->post('mutaion', '');
		if (!$mutation) throw new MutationCheckException('为空', 1);
		$param = $request->post('params', '');
		if (!$param) throw new MutationCheckException('格式错误', 2);
		$result = [];
		//获取handler
		$handler = $this->reflectRecords[$mutation] ?? '';
		//获取结果
		$result = is_callable($handler) ? $handler($request, $param) : '';
		$return = $result ?  [
			'code' => 0,
			'msg' => '操作成功',
			'data' => $result
		] : [
			'code' => -1,
			'msg' => '无此接口',
			'data' => ''
		];
		return new Response(200, ['Content-Type' => 'application/json'], json_encode($return, JSON_UNESCAPED_UNICODE));
	}
}
