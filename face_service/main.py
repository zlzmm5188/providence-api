#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Providence 本地人脸比对服务
使用 InsightFace 进行人脸识别，完全本地化，不依赖任何云服务
"""

import os
import io
import cv2
import numpy as np
from typing import Optional, Tuple
from datetime import datetime

from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import BaseModel
import uvicorn

# 设置模型缓存目录（本地保存，不向外网下载）
os.environ['INSIGHTFACE_HOME'] = os.path.join(os.path.dirname(__file__), 'models')

import insightface
from insightface.app import FaceAnalysis

# ========== 配置 ==========
CONFIG = {
    'host': '0.0.0.0',
    'port': 9000,
    'similarity_threshold': 0.80,  # 相似度阈值
    'min_face_size': 80,           # 最小人脸尺寸（像素）
    'min_face_ratio': 0.05,        # 人脸占图片最小比例
    'blur_threshold': 100,         # 模糊度阈值（越大越清晰）
    'brightness_min': 40,          # 最小亮度
    'brightness_max': 220,         # 最大亮度
    'model_name': 'buffalo_l',     # InsightFace 模型
    'log_dir': 'logs'
}

# ========== 初始化 ==========
app = FastAPI(
    title="Providence 人脸比对服务",
    description="本地化人脸识别比对服务，不依赖任何云服务",
    version="1.0.0"
)

# CORS 配置
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# 全局人脸分析器
face_app: Optional[FaceAnalysis] = None


def init_face_analyzer():
    """初始化人脸分析器"""
    global face_app
    if face_app is None:
        print("[FaceService] 正在加载人脸识别模型...")
        face_app = FaceAnalysis(
            name=CONFIG['model_name'],
            root=os.environ['INSIGHTFACE_HOME'],
            providers=['CPUExecutionProvider']  # 使用 CPU，如有 GPU 可改为 CUDAExecutionProvider
        )
        face_app.prepare(ctx_id=0, det_size=(640, 640))
        print("[FaceService] 模型加载完成")
    return face_app


# ========== 响应模型 ==========
class FaceVerifyResponse(BaseModel):
    code: int
    msg: str
    data: Optional[dict] = None


# ========== 工具函数 ==========
def read_image_from_upload(file: UploadFile) -> np.ndarray:
    """从上传文件读取图片"""
    contents = file.file.read()
    nparr = np.frombuffer(contents, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    if img is None:
        raise ValueError("无法解析图片文件")
    return img


def calculate_blur(img: np.ndarray) -> float:
    """计算图片模糊度（拉普拉斯方差）"""
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    return cv2.Laplacian(gray, cv2.CV_64F).var()


def calculate_brightness(img: np.ndarray) -> float:
    """计算图片平均亮度"""
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    return np.mean(gray)


def check_image_quality(img: np.ndarray, name: str) -> Tuple[bool, str]:
    """检查图片质量"""
    h, w = img.shape[:2]

    # 检查尺寸
    if w < 100 or h < 100:
        return False, f"{name}图片尺寸太小，请上传更清晰的照片"

    # 检查模糊度
    blur = calculate_blur(img)
    if blur < CONFIG['blur_threshold']:
        return False, f"{name}图片过于模糊，请上传更清晰的照片"

    # 检查亮度
    brightness = calculate_brightness(img)
    if brightness < CONFIG['brightness_min']:
        return False, f"{name}图片过暗，请在光线充足的环境下拍摄"
    if brightness > CONFIG['brightness_max']:
        return False, f"{name}图片过亮，请避免过度曝光"

    return True, ""


def detect_face(img: np.ndarray, name: str) -> Tuple[Optional[np.ndarray], str]:
    """检测人脸并返回特征向量"""
    analyzer = init_face_analyzer()
    faces = analyzer.get(img)

    if not faces:
        return None, f"未在{name}中检测到人脸"

    # 选择最大的人脸
    largest_face = max(faces, key=lambda x: (x.bbox[2] - x.bbox[0]) * (x.bbox[3] - x.bbox[1]))

    # 检查人脸大小
    bbox = largest_face.bbox
    face_w = bbox[2] - bbox[0]
    face_h = bbox[3] - bbox[1]
    img_h, img_w = img.shape[:2]

    if face_w < CONFIG['min_face_size'] or face_h < CONFIG['min_face_size']:
        return None, f"{name}中人脸太小，请靠近摄像头或上传更清晰的照片"

    face_ratio = (face_w * face_h) / (img_w * img_h)
    if face_ratio < CONFIG['min_face_ratio']:
        return None, f"{name}中人脸占比太小，请确保人脸清晰可见"

    # 返回 512 维特征向量
    return largest_face.embedding, ""


def cosine_similarity(vec1: np.ndarray, vec2: np.ndarray) -> float:
    """计算余弦相似度"""
    vec1 = vec1 / np.linalg.norm(vec1)
    vec2 = vec2 / np.linalg.norm(vec2)
    return float(np.dot(vec1, vec2))


def log_verification(selfie_name: str, id_photo_name: str, similarity: float, passed: bool, error: str = ""):
    """记录验证日志"""
    log_dir = os.path.join(os.path.dirname(__file__), CONFIG['log_dir'])
    os.makedirs(log_dir, exist_ok=True)

    log_file = os.path.join(log_dir, f"{datetime.now().strftime('%Y-%m-%d')}.log")
    timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    log_line = f"{timestamp} | selfie={selfie_name} | id_photo={id_photo_name} | similarity={similarity:.4f} | passed={passed}"
    if error:
        log_line += f" | error={error}"
    log_line += "\n"

    with open(log_file, 'a', encoding='utf-8') as f:
        f.write(log_line)


# ========== API 接口 ==========
@app.get("/")
async def root():
    """健康检查"""
    return {"status": "ok", "service": "Providence Face Verification", "version": "1.0.0"}


@app.get("/health")
async def health():
    """健康检查"""
    return {"status": "ok", "model_loaded": face_app is not None}


@app.post("/face/verify", response_model=FaceVerifyResponse)
async def verify_face(
    selfie: UploadFile = File(..., description="自拍照"),
    id_photo: UploadFile = File(..., description="身份证照片")
):
    """
    人脸比对接口

    - selfie: 用户自拍照
    - id_photo: 身份证正面照片（包含人脸）

    返回相似度和是否通过验证
    """
    similarity = 0.0
    passed = False

    try:
        # 1. 读取图片
        try:
            selfie_img = read_image_from_upload(selfie)
        except Exception as e:
            log_verification(selfie.filename, id_photo.filename, 0, False, f"自拍照读取失败: {e}")
            return FaceVerifyResponse(code=-1, msg="自拍照读取失败，请重新上传", data=None)

        try:
            id_photo_img = read_image_from_upload(id_photo)
        except Exception as e:
            log_verification(selfie.filename, id_photo.filename, 0, False, f"身份证照片读取失败: {e}")
            return FaceVerifyResponse(code=-1, msg="身份证照片读取失败，请重新上传", data=None)

        # 2. 图片质量检测
        quality_ok, quality_msg = check_image_quality(selfie_img, "自拍照")
        if not quality_ok:
            log_verification(selfie.filename, id_photo.filename, 0, False, quality_msg)
            return FaceVerifyResponse(code=-1, msg=quality_msg, data=None)

        quality_ok, quality_msg = check_image_quality(id_photo_img, "身份证照片")
        if not quality_ok:
            log_verification(selfie.filename, id_photo.filename, 0, False, quality_msg)
            return FaceVerifyResponse(code=-1, msg=quality_msg, data=None)

        # 3. 人脸检测和特征提取
        selfie_embedding, error = detect_face(selfie_img, "自拍照")
        if selfie_embedding is None:
            log_verification(selfie.filename, id_photo.filename, 0, False, error)
            return FaceVerifyResponse(code=-1, msg=error, data=None)

        id_embedding, error = detect_face(id_photo_img, "身份证照片")
        if id_embedding is None:
            log_verification(selfie.filename, id_photo.filename, 0, False, error)
            return FaceVerifyResponse(code=-1, msg=error, data=None)

        # 4. 计算相似度
        similarity = cosine_similarity(selfie_embedding, id_embedding)
        passed = similarity >= CONFIG['similarity_threshold']

        # 5. 记录日志
        log_verification(selfie.filename, id_photo.filename, similarity, passed)

        # 6. 返回结果
        return FaceVerifyResponse(
            code=1,
            msg="ok" if passed else "人脸比对未通过",
            data={
                "similarity": round(similarity, 4),
                "passed": passed,
                "threshold": CONFIG['similarity_threshold']
            }
        )

    except Exception as e:
        log_verification(
            selfie.filename if selfie else "unknown",
            id_photo.filename if id_photo else "unknown",
            0, False, str(e)
        )
        return FaceVerifyResponse(code=-1, msg=f"服务器内部错误: {str(e)}", data=None)


@app.post("/face/quality")
async def check_quality(image: UploadFile = File(..., description="待检测图片")):
    """
    图片质量检测接口（辅助接口）
    """
    try:
        img = read_image_from_upload(image)

        h, w = img.shape[:2]
        blur = calculate_blur(img)
        brightness = calculate_brightness(img)

        # 检测人脸
        analyzer = init_face_analyzer()
        faces = analyzer.get(img)
        face_count = len(faces)

        face_info = None
        if faces:
            largest_face = max(faces, key=lambda x: (x.bbox[2] - x.bbox[0]) * (x.bbox[3] - x.bbox[1]))
            bbox = largest_face.bbox
            face_info = {
                "x": int(bbox[0]),
                "y": int(bbox[1]),
                "width": int(bbox[2] - bbox[0]),
                "height": int(bbox[3] - bbox[1])
            }

        return {
            "code": 1,
            "msg": "ok",
            "data": {
                "image_size": {"width": w, "height": h},
                "blur_score": round(blur, 2),
                "brightness": round(brightness, 2),
                "face_count": face_count,
                "face_info": face_info,
                "quality_passed": blur >= CONFIG['blur_threshold'] and
                                  CONFIG['brightness_min'] <= brightness <= CONFIG['brightness_max'] and
                                  face_count > 0
            }
        }

    except Exception as e:
        return {"code": -1, "msg": str(e), "data": None}


# ========== 启动入口 ==========
if __name__ == "__main__":
    print("=" * 50)
    print("Providence 本地人脸比对服务")
    print("=" * 50)
    print(f"监听地址: {CONFIG['host']}:{CONFIG['port']}")
    print(f"相似度阈值: {CONFIG['similarity_threshold']}")
    print("完全本地化，不依赖任何云服务")
    print("=" * 50)

    # 预加载模型
    init_face_analyzer()

    # 启动服务
    uvicorn.run(
        "main:app",
        host=CONFIG['host'],
        port=CONFIG['port'],
        reload=False,
        log_level="info"
    )
