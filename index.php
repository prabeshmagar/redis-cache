<?php

require 'vendor/autoload.php';

$app = new \Slim\App([
    'settings' => [
        'displayErrorDetails' => true
    ]
]);

$container = $app->getContainer();

$container['config'] = function () {
    return new Noodlehaus\Config([
        __DIR__ . '/config/cache.php',
    ]);
};

$container['db'] = function () {
    return new PDO('mysql:host=localhost;dbname=project', 'root', '');
};

$container['http'] = function () {
    return new \GuzzleHttp\Client;
};

$container['cache'] = function ($c) {
    $client = new \Predis\Client([
        'scheme' => 'tcp',
        'host' => $c->config->get('cache.connections.redis.host'),
        'port' => $c->config->get('cache.connections.redis.port'),
        'password' => $c->config->get('cache.connections.redis.password')
    ]);

    return new \App\Cache\RedisAdapter($client);
};


$app->get('/users', function($request, $response) {
    $users = $this->cache->remember('users',10, function() {
        return json_encode($this->db->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC));
    });

    return $response->withHeader('Content-Type','application/json')->write($users);
});


$app->get('/hn', function($request, $response) {
    $stories = $this->cache->remember('hn:top-stories',10, function(){
        $res = $this->http->request('GET', 'https://hacker-news.firebaseio.com/v0/topstories.json');
        
         $stories = [];
    
    foreach(array_slice(json_decode($res->getBody()),0,15) as $storyId) {
        $res = $this->http->request('GET', 'https://hacker-news.firebaseio.com/v0/item/'.$storyId.'.json');
        
        $stories[] = json_decode($res->getBody());
    }
     return json_encode($stories);
    });

 
    return $response->withHeader('Content-Type','application/json')->write($stories);
});

$app->run();