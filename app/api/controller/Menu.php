<?php
namespace app\api\controller;

use think\facade\Request;

class Menu
{
    /**
     * 获取所有菜单（管理后台）
     */
    public function all()
    {
        // 验证token（简单验证）
        $token = Request::header('Authorization') ?? Request::header('Token') ?? '';
        
        if (empty($token)) {
            return json([
                "code" => 401,
                "message" => "未授权",
                "data" => []
            ]);
        }

        // 返回完整的管理后台菜单
        $menus = [
            [
                "id" => "dashboard",
                "parentId" => "",
                "name" => "Dashboard",
                "component" => "layouts/basic-layout",
                "path" => "/dashboard",
                "meta" => [
                    "title" => "数据概览",
                    "icon" => "lucide:layout-dashboard",
                    "order" => 1
                ],
                "children" => [
                    [
                        "id" => "dashboard_overview",
                        "parentId" => "dashboard",
                        "name" => "DashboardOverview",
                        "component" => "views/dashboard/overview",
                        "path" => "/dashboard/overview",
                        "meta" => [
                            "title" => "数据概览",
                            "icon" => "lucide:trending-up"
                        ]
                    ]
                ]
            ],
            [
                "id" => "users",
                "parentId" => "",
                "name" => "Users",
                "component" => "layouts/basic-layout",
                "path" => "/users",
                "meta" => [
                    "title" => "用户管理",
                    "icon" => "lucide:users",
                    "order" => 2
                ],
                "children" => [
                    [
                        "id" => "users_list",
                        "parentId" => "users",
                        "name" => "UsersList",
                        "component" => "views/users/list",
                        "path" => "/users/list",
                        "meta" => [
                            "title" => "用户列表",
                            "icon" => "lucide:user"
                        ]
                    ],
                    [
                        "id" => "users_kyc",
                        "parentId" => "users",
                        "name" => "UsersKyc",
                        "component" => "views/users/kyc",
                        "path" => "/users/kyc",
                        "meta" => [
                            "title" => "实名认证",
                            "icon" => "lucide:shield-check"
                        ]
                    ]
                ]
            ],
            [
                "id" => "finance",
                "parentId" => "",
                "name" => "Finance",
                "component" => "layouts/basic-layout",
                "path" => "/finance",
                "meta" => [
                    "title" => "财务管理",
                    "icon" => "lucide:wallet",
                    "order" => 3
                ],
                "children" => [
                    [
                        "id" => "finance_recharge",
                        "parentId" => "finance",
                        "name" => "FinanceRecharge",
                        "component" => "views/finance/recharge",
                        "path" => "/finance/recharge",
                        "meta" => [
                            "title" => "充值管理",
                            "icon" => "lucide:arrow-down-circle"
                        ]
                    ],
                    [
                        "id" => "finance_withdraw",
                        "parentId" => "finance",
                        "name" => "FinanceWithdraw",
                        "component" => "views/finance/withdraw",
                        "path" => "/finance/withdraw",
                        "meta" => [
                            "title" => "提现管理",
                            "icon" => "lucide:arrow-up-circle"
                        ]
                    ],
                    [
                        "id" => "finance_earnings",
                        "parentId" => "finance",
                        "name" => "FinanceEarnings",
                        "component" => "views/finance/earnings",
                        "path" => "/finance/earnings",
                        "meta" => [
                            "title" => "收益记录",
                            "icon" => "lucide:trending-up"
                        ]
                    ]
                ]
            ],
            [
                "id" => "vip",
                "parentId" => "",
                "name" => "VIP",
                "component" => "layouts/basic-layout",
                "path" => "/vip",
                "meta" => [
                    "title" => "VIP管理",
                    "icon" => "lucide:crown",
                    "order" => 4
                ],
                "children" => [
                    [
                        "id" => "vip_list",
                        "parentId" => "vip",
                        "name" => "VipList",
                        "component" => "views/vip/list",
                        "path" => "/vip/list",
                        "meta" => [
                            "title" => "VIP等级",
                            "icon" => "lucide:star"
                        ]
                    ]
                ]
            ],
            [
                "id" => "content",
                "parentId" => "",
                "name" => "Content",
                "component" => "layouts/basic-layout",
                "path" => "/content",
                "meta" => [
                    "title" => "内容管理",
                    "icon" => "lucide:file-text",
                    "order" => 5
                ],
                "children" => [
                    [
                        "id" => "content_announcements",
                        "parentId" => "content",
                        "name" => "ContentAnnouncements",
                        "component" => "views/content/announcements",
                        "path" => "/content/announcements",
                        "meta" => [
                            "title" => "公告管理",
                            "icon" => "lucide:megaphone"
                        ]
                    ],
                    [
                        "id" => "content_activities",
                        "parentId" => "content",
                        "name" => "ContentActivities",
                        "component" => "views/content/activities",
                        "path" => "/content/activities",
                        "meta" => [
                            "title" => "活动管理",
                            "icon" => "lucide:gift"
                        ]
                    ]
                ]
            ],
            [
                "id" => "audit",
                "parentId" => "",
                "name" => "Audit",
                "component" => "layouts/basic-layout",
                "path" => "/audit",
                "meta" => [
                    "title" => "审核管理",
                    "icon" => "lucide:check-circle",
                    "order" => 6
                ],
                "children" => [
                    [
                        "id" => "audit_list",
                        "parentId" => "audit",
                        "name" => "AuditList",
                        "component" => "views/audit/list",
                        "path" => "/audit/list",
                        "meta" => [
                            "title" => "审核列表",
                            "icon" => "lucide:list-checks"
                        ]
                    ]
                ]
            ],
            [
                "id" => "settings",
                "parentId" => "",
                "name" => "Settings",
                "component" => "layouts/basic-layout",
                "path" => "/settings",
                "meta" => [
                    "title" => "系统设置",
                    "icon" => "lucide:settings",
                    "order" => 99
                ],
                "children" => [
                    [
                        "id" => "settings_config",
                        "parentId" => "settings",
                        "name" => "SettingsConfig",
                        "component" => "views/settings/config",
                        "path" => "/settings/config",
                        "meta" => [
                            "title" => "系统配置",
                            "icon" => "lucide:sliders"
                        ]
                    ],
                    [
                        "id" => "settings_risk",
                        "parentId" => "settings",
                        "name" => "SettingsRisk",
                        "component" => "views/settings/risk",
                        "path" => "/settings/risk",
                        "meta" => [
                            "title" => "风控设置",
                            "icon" => "lucide:shield"
                        ]
                    ]
                ]
            ]
        ];

        return json([
            "code" => 0,
            "message" => "ok",
            "data" => $menus
        ]);
    }
}

