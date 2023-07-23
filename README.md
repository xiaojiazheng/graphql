<h1>图表查询和变更服务</h1>

<p>该本插件提供了在 Webman 环境下使用 Laravel ORM 模型进行图表查询和变更的功能。</p>

<h2>安装</h2>

<p>通过 Composer 来安装该服务：</p>

<pre><code>composer require xiaobe/graphql
</code></pre>

<h2>查询配置</h2>

<p>本项目是webman插件，确认是否配置数据库连接即可</p>

<p>新建查询模型，继承<code>QueryModel</code></p>

<p>新建一个控制器，实现<code>GraphQLService</code>抽象类</p>

<p>配置查询模型映射，即可启动</p>

<p>可选配置查询模型字段禁用</p>

<h2>变更服务配置</h2>

<p>暂定</p>

<h2>使用方法</h2>

<h3>搭建服务</h3>

<p>在控制器目录中创建一个新的控制器继承<code>GraphQLService</code>服务，实现两个set方法</p>

<p>在你的控制器里中定义查询模型映射和每个模型禁用的字段：</p>

<pre><code>
namespace  Xiaobe\Graphql\test;

use Xiaobe\Graphql\root\GraphQLService;

#### 介绍
webman库
请使用laraval ORM 其他自行兼容
php 7.4

#### 软件架构
软件架构说明


#### 安装教程

1.  xxxx
2.  xxxx
3.  xxxx

#### 使用说明

1.  xxxx
2.  xxxx
3.  xxxx

#### 参与贡献

1.  Fork 本仓库
2.  新建 Feat_xxx 分支
3.  提交代码
4.  新建 Pull Request


#### 特技

1.  使用 Readme\_XXX.md 来支持不同的语言，例如 Readme\_en.md, Readme\_zh.md
2.  Gitee 官方博客 [blog.gitee.com](https://blog.gitee.com)
3.  你可以 [https://gitee.com/explore](https://gitee.com/explore) 这个地址来了解 Gitee 上的优秀开源项目
4.  [GVP](https://gitee.com/gvp) 全称是 Gitee 最有价值开源项目，是综合评定出的优秀开源项目
5.  Gitee 官方提供的使用手册 [https://gitee.com/help](https://gitee.com/help)
6.  Gitee 封面人物是一档用来展示 Gitee 会员风采的栏目 [https://gitee.com/gitee-stars/](https://gitee.com/gitee-stars/)

许可证
该项目基于 MIT 许可证进行分发。更多信息请参阅 LICENSE 文件。