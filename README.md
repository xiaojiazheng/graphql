图表查询和变更服务
该服务提供了在 Webman 环境下使用 Laravel ORM 模型进行图表查询和变更的功能。

安装
通过 Composer 来安装该服务：

shell
composer require xiaobe/graphql
配置
在 config/graphql.php 文件中配置数据库连接信息：

php
'database' => [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'your_database_name',
            'username' => 'your_username',
            'password' => 'your_password',
        ],
    ],
],
替换 your_database_name、your_username 和 your_password 分别为你的数据库的名称、用户名和密码。

在 config/graphql.php 文件中配置 Laravel ORM 模型的命名空间：

php
'namespaces' => [
    'models' => [
        'App\\Models',
    ],
],
确保将 App\\Models 替换为你的实际模型命名空间。

使用方法
查询数据
在 app/GraphQL/Queries 目录中创建一个新的查询类，例如 ChartQuery.php。

在你的查询类中定义查询字段和逻辑：

php
<?php

namespace App\GraphQL\Queries;

use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Query;
use GraphQL\Type\Definition\Type;
use App\Models\YourModel;

class ChartQuery extends Query
{
    protected $attributes = [
        'name' => 'chart',
    ];

    public function type(): Type
    {
        return GraphQL::type('YourResultType');
    }

    public function args(): array
    {
        return [
            // 定义查询参数
            'yourParameter' => ['name' => 'yourParameter', 'type' => Type::string()],
        ];
    }

    public function resolve($root, $args)
    {
        // 查询逻辑
        $query = YourModel::query();

        if (isset($args['yourParameter'])) {
            $query->where('your_column', $args['yourParameter']);
        }

        return $query->get();
    }
}
在 config/graphql.php 文件的 schemas 配置中注册你的查询类：

php
'schemas' => [
    'default' => [
        'query' => [
            \App\GraphQL\Queries\ChartQuery::class,
        ],
        // ...
    ],
],
现在，你可以使用 GraphQL 查询语言来查询数据，并根据需要进行过滤和排序。

变更数据
在 app/GraphQL/Mutations 目录中创建一个新的变更类，例如 DataMutation.php。

在你的变更类中定义变更字段和逻辑：

php
<?php

namespace App\GraphQL\Mutations;

use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Mutation;
use GraphQL\Type\Definition\Type;
use App\Models\YourModel;

class DataMutation extends Mutation
{
    protected $attributes = [
        'name' => 'dataMutation',
    ];

    public function type(): Type
    {
        return GraphQL::type('YourResultType');
    }

    public function args(): array
    {
        return [
            // 定义变更参数
            'yourParameter' => ['name' => 'yourParameter', 'type' => Type::string(), 'rules' => ['required']],
        ];
    }

    public function resolve($root, $args)
    {
        // 变更逻辑
        $data = new YourModel();
        $data->your_column = $args['yourParameter'];
        $data->save();

        return $data;
    }
}
在 config/graphql.php 文件的 schemas 配置中注册你的变更类：

php
'schemas' => [
    'default' => [
        'mutation' => [
            \App\GraphQL\Mutations\DataMutation::class,
        ],
        // ...
    ],
],
现在，你可以使用 GraphQL 变更语言来创建新的数据和更新数据。

贡献
欢迎对该项目进行贡献。如果你发现了问题或有改进建议，请提交 Issue 或 Pull Request。

许可证
该项目基于 MIT 许可证进行分发。更多信息请参阅 LICENSE 文件。