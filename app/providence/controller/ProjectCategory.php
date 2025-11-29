<?php
/**
 * 项目板块管理控制器
 */

namespace app\providence\controller;

use think\facade\Db;

class ProjectCategory
{
    /**
     * 获取项目板块列表
     * GET /providence/project-categories
     */
    public function index()
    {
        $list = Db::name('project_category')
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select();

        return success('获取成功', $list);
    }

    /**
     * 创建项目板块
     * POST /providence/project-categories
     */
    public function create()
    {
        $name = input('post.name', '', 'trim');

        if (empty($name)) {
            return error('板块名称不能为空');
        }

        try {
            $id = Db::name('project_category')->insertGetId([
                'name' => $name,
                'value' => strtolower(preg_replace('/\s+/', '_', $name)),
                'sort' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $category = Db::name('project_category')->find($id);

            return success('创建成功', $category);
        } catch (\Exception $e) {
            return error('创建失败：' . $e->getMessage());
        }
    }

    /**
     * 删除项目板块
     * DELETE /providence/project-categories/:id
     */
    public function delete()
    {
        $id = input('param.id', 0, 'intval');

        // 检查是否有项目使用该板块
        $projectCount = Db::name('invest_project')->where('category', $id)->count();
        if ($projectCount > 0) {
            return error('该板块下还有项目，无法删除');
        }

        try {
            Db::name('project_category')->where('id', $id)->delete();

            return success('删除成功');
        } catch (\Exception $e) {
            return error('删除失败：' . $e->getMessage());
        }
    }
}
