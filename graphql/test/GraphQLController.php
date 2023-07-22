<?php

namespace  Xiaobe\graphql\test;

use Xiaobe\graphql\root\GraphQLService;

class GraphQLController extends GraphQLService
{
    protected function setMapping()
    {
        // $this->modelMapper->addMapping('bill_receive', Receive::class);
        // $this->modelMapper->addMapping('bill_delivery', Delivery::class);
    }
}
