# Providence 本地人脸比对服务

基于 InsightFace 的本地化人脸比对服务，**完全本地运行，不依赖任何云服务，不向外网上传图片**。

## 功能特性

- ✅ 自拍照 vs 证件照人脸比对
- ✅ 图片质量检测（模糊度、亮度）
- ✅ 人脸检测和特征提取（512维向量）
- ✅ 余弦相似度计算
- ✅ 完全本地化，隐私安全

## 系统要求

- Python 3.8+
- 2GB+ RAM
- 4GB+ 磁盘空间（模型文件）

## 快速安装

```bash
cd /www/wwwroot/api.4kp3l0iq.top/face_service

# 创建虚拟环境
python3 -m venv venv
source venv/bin/activate

# 安装依赖
pip install -r requirements.txt

# 首次运行会自动下载模型到 models/ 目录
python main.py
```

## 启动方式

### 方式1：直接运行

```bash
source venv/bin/activate
uvicorn main:app --host 0.0.0.0 --port 9000
```

### 方式2：使用启动脚本

```bash
chmod +x start.sh
./start.sh
```

### 方式3：Systemd 服务（推荐生产环境）

```bash
# 复制服务配置
sudo cp face-service.service /etc/systemd/system/

# 重载配置
sudo systemctl daemon-reload

# 启动服务
sudo systemctl start face-service

# 开机启动
sudo systemctl enable face-service

# 查看状态
sudo systemctl status face-service

# 查看日志
sudo journalctl -u face-service -f
```

### 方式4：PM2 管理

```bash
# 安装 PM2
npm install -g pm2

# 启动服务
pm2 start "source venv/bin/activate && uvicorn main:app --host 0.0.0.0 --port 9000" --name face-service

# 保存配置
pm2 save

# 设置开机启动
pm2 startup
```

## API 接口

### 1. 人脸比对

**POST /face/verify**

```bash
curl -X POST "http://localhost:9000/face/verify" \
  -F "selfie=@selfie.jpg" \
  -F "id_photo=@id_card.jpg"
```

**响应示例（成功）：**

```json
{
  "code": 1,
  "msg": "ok",
  "data": {
    "similarity": 0.87,
    "passed": true,
    "threshold": 0.8
  }
}
```

**响应示例（失败）：**

```json
{
  "code": -1,
  "msg": "未在自拍照中检测到人脸",
  "data": null
}
```

### 2. 图片质量检测

**POST /face/quality**

```bash
curl -X POST "http://localhost:9000/face/quality" \
  -F "image=@photo.jpg"
```

### 3. 健康检查

**GET /health**

```bash
curl http://localhost:9000/health
```

## Nginx 反向代理配置

将请求从 `https://api.4kp3l0iq.top/face/verify` 代理到本服务：

```nginx
# 在 api.4kp3l0iq.top 的 server 块中添加：

location /face/ {
    proxy_pass http://127.0.0.1:9000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    # 文件上传超时设置
    proxy_connect_timeout 60s;
    proxy_send_timeout 60s;
    proxy_read_timeout 60s;

    # 文件大小限制
    client_max_body_size 20M;
}
```

## 目录结构

```
face_service/
├── main.py              # 主程序
├── requirements.txt     # Python 依赖
├── start.sh            # 启动脚本
├── face-service.service # Systemd 配置
├── README.md           # 说明文档
├── models/             # InsightFace 模型文件（自动下载）
│   └── buffalo_l/
└── logs/               # 验证日志
    └── 2025-11-25.log
```

## 配置说明

在 `main.py` 中可调整以下参数：

```python
CONFIG = {
    'host': '0.0.0.0',
    'port': 9000,
    'similarity_threshold': 0.80,  # 相似度阈值（0.8 = 80%）
    'min_face_size': 80,           # 最小人脸尺寸（像素）
    'min_face_ratio': 0.05,        # 人脸占图片最小比例
    'blur_threshold': 100,         # 模糊度阈值
    'brightness_min': 40,          # 最小亮度
    'brightness_max': 220,         # 最大亮度
}
```

## 安全说明

- ✅ 所有图片处理都在本地完成
- ✅ 不向任何外部服务器发送数据
- ✅ 模型文件保存在本地 `models/` 目录
- ✅ 验证日志保存在本地 `logs/` 目录
- ✅ 建议仅允许内网访问（127.0.0.1）

## 故障排除

1. **模型下载失败**
   - 首次运行需要下载约 300MB 模型文件
   - 如网络问题，可手动下载放入 `models/` 目录

2. **内存不足**
   - InsightFace 需要约 1.5GB 内存
   - 确保服务器有足够内存

3. **CPU 使用率高**
   - 人脸识别是计算密集型任务
   - 如有 GPU，可修改为 CUDA 加速

## 许可证

仅供 Providence 项目内部使用
