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

class GraphQLController extends GraphQLService
{
    protected function setMapping()
    {
        // $this->modelMapper->addMapping('bill_receive', Receive::class);
        // $this->modelMapper->addMapping('bill_delivery', Delivery::class);
    }
    protected function setDisabled()
    {
        // $this->addQueryDisabled('bill_receive', []);
        // $this->addMutationDisabled('bill_receive', []);
    }
}
</code></pre>

<p>现在，你可以使用 基于GraphQL的语言 来查询数据，并根据需要进行过滤和排序。</p>

<h3>变更数据</h3>

暂定

<p>现在，你可以使用基于GraphQL的语言 来创建新的数据和更新数据。</p>

<h2>基于GraphQL的语言语法</h2>

<p>暂定</p>

<h2>安装</h2>

<h2>贡献</h2>

<p>欢迎对该项目进行贡献。如果你发现了问题或有改进建议，请提交 Issue 或 Pull Request。</p>

<h2>许可证</h2>

<p>该项目基于 MIT 许可证进行分发。更多信息请参阅 LICENSE 文件。</p>
