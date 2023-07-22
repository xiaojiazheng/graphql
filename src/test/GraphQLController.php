<?php

namespace  Xiaobe\Graphql\test;

use Xiaobe\Graphql\root\GraphQLService;

class GraphQLController extends GraphQLService
{
    protected function setMapping()
    {
        // $this->modelMapper->addMapping('bill_receive', Receive::class);
        // $this->modelMapper->addMapping('bill_delivery', Delivery::class);
    }
}
