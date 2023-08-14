<?php

namespace xiaobe\Graphql\root;

use support\Request;
use support\Response;
use Xiaobe\Graphql\exception\QueryCheckException;

/**
 * 查询 控制器 抽象类
 */
abstract class QueryApi
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
	 * 为一个查询设置一个处理函数
	 * @param string $query 查询参数
	 * @param string $service 服务类的完全限定名称
	 */
	protected function addRecord(string $query, $handler)
	{
		$this->reflectRecords[$query] = $handler;
	}
	public function index(Request $request)
	{
		$query = $request->input('query', '');
		if (!$query) throw new QueryCheckException('为空', 1);
		//兼容多种输入
		$queryArray = is_array($query) ? $query : explode(',', $query);
		if (!$queryArray) throw new QueryCheckException('格式错误', 2);
		$result = [];
		foreach ($queryArray as $item) {
			//去除空干扰
			if (!$item) continue;
			//获取handler
			$handler = $this->reflectRecords[$item] ?? '';
			//获取handler参数
			$params = $this->reflectRecords[$item . 'params'] ?? [];
			//获取结果
			$result[$item] = is_callable($handler) ? $handler($request, $params) : '无此接口';
		}
		$return = [
			'code' => 0,
			'msg' => '获取成功',
			'data' => $result
		];
		return new Response(200, ['Content-Type' => 'application/json'], json_encode($return, JSON_UNESCAPED_UNICODE));
	}
}
