<?php

namespace  xiaobe\Graphql\test;

use xiaobe\Graphql\root\GraphQLService;

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
