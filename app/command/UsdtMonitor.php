<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\common\service\UsdtService;

/**
 * USDT到账监控定时任务
 * 使用方法：php think usdt:monitor
 */
class UsdtMonitor extends Command
{
    protected function configure()
    {
        $this->setName('usdt:monitor')
            ->setDescription('监控USDT到账');
    }
    
    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始监控USDT到账...');
        
        try {
            UsdtService::monitorTransactions();
            $output->writeln('[' . date('Y-m-d H:i:s') . '] USDT监控完成');
        } catch (\Exception $e) {
            $output->error('[' . date('Y-m-d H:i:s') . '] 监控失败: ' . $e->getMessage());
        }
    }
}
