<?php
namespace app\api\controller;

class Fund
{
    /**
     * 处理 /api/fund/project/all
     */
    public function project()
    {
        // 直接调用 Project 控制器的 index 方法
        $projectController = new Project();
        return $projectController->index();
    }
}
