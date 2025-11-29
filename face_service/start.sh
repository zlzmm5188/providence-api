#!/bin/bash
# Providence 人脸比对服务启动脚本

cd "$(dirname "$0")"

# 检查虚拟环境
if [ ! -d "venv" ]; then
    echo "创建 Python 虚拟环境..."
    python3 -m venv venv
fi

# 激活虚拟环境
source venv/bin/activate

# 安装依赖
pip install -r requirements.txt -q

# 启动服务
echo "启动 Providence 人脸比对服务..."
python main.py
