<?php

namespace Demo\Controller;

use Think\Controller;

class TestController extends Controller
{
    public function hello()
    {
        echo 'hello,world';
    }
    public function gucci()
    {
        echo 'hello,gucci';
    }
}