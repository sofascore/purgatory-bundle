<?php return array (
  'Foo' => 
  array (
    0 => 
    array (
      'routeName' => 'app_route_foo',
      'actions' => 
      array (
        0 => 
        %SSofascore\PurgatoryBundle\Listener\Enum\Action::Update,
      ),
    ),
  ),
  'Foo::bar' => 
  array (
    0 => 
    array (
      'routeName' => 'app_route_foo',
      'routeParams' => 
      array (
        'bar' => 
        array (
          'type' => 'property',
          'values' => 
          array (
            0 => 'bar.id',
          ),
        ),
      ),
      'actions' => 
      array (
        0 => 
        %SSofascore\PurgatoryBundle\Listener\Enum\Action::Create,
      ),
    ),
  ),
);