#!/bin/bash
# API Smoke Test Script
# 用于测试后台管理系统的关键接口是否正常

BASE="https://api.4kp3l0iq.top/api"
REPORT="API_SMOKE_TEST_RESULT.md"

echo "# API Smoke Test Result" > "$REPORT"
echo "" >> "$REPORT"
echo "**测试时间**: $(date '+%Y-%m-%d %H:%M:%S')" >> "$REPORT"
echo "**测试环境**: Production (api.4kp3l0iq.top)" >> "$REPORT"
echo "" >> "$REPORT"
echo "---" >> "$REPORT"
echo "" >> "$REPORT"

# 定义测试接口列表
endpoints=(
  "providence/ribao/stats"
  "providence/ribao/records?page=1&pageSize=10"
  "providence/ribao/users?page=1&pageSize=10"
  "providence/rebate/stats"
  "providence/rebate/list?page=1&pageSize=10"
)

# 统计结果
PASS=0
FAIL=0

echo "开始测试接口..."
echo ""

for ep in "${endpoints[@]}"; do
  URL="$BASE/$ep"
  echo "Testing: $URL"

  # 执行请求
  HTTP_CODE=$(curl -k -s -o /tmp/api_test_body.txt -w "%{http_code}" "$URL")
  BODY=$(cat /tmp/api_test_body.txt)

  # 写入报告
  echo "## $ep" >> "$REPORT"
  echo "" >> "$REPORT"
  echo "**URL**: \`$URL\`" >> "$REPORT"
  echo "**HTTP状态码**: \`$HTTP_CODE\`" >> "$REPORT"
  echo "" >> "$REPORT"

  if [ "$HTTP_CODE" = "200" ]; then
    # 检查响应是否为JSON格式
    if echo "$BODY" | grep -q "code"; then
      echo "**状态**: ✅ 通过" >> "$REPORT"
      echo "**响应体**: \`\`\`json" >> "$REPORT"
      echo "$BODY" | head -c 500 >> "$REPORT"
      echo "" >> "$REPORT"
      echo "\`\`\`" >> "$REPORT"
      echo "✅ 通过: $ep"
      ((PASS++))
    else
      echo "**状态**: ⚠️ 警告（非JSON格式）" >> "$REPORT"
      echo "**响应体**: \`\`\`" >> "$REPORT"
      echo "$BODY" | head -c 500 >> "$REPORT"
      echo "" >> "$REPORT"
      echo "\`\`\`" >> "$REPORT"
      echo "⚠️  警告: $ep (非JSON格式)"
      ((FAIL++))
    fi
  else
    echo "**状态**: ❌ 失败" >> "$REPORT"
    echo "**错误信息**: HTTP $HTTP_CODE" >> "$REPORT"
    echo "**响应体**: \`\`\`" >> "$REPORT"
    echo "$BODY" | head -c 500 >> "$REPORT"
    echo "" >> "$REPORT"
    echo "\`\`\`" >> "$REPORT"
    echo "❌ 失败: $ep (HTTP $HTTP_CODE)"
    ((FAIL++))
  fi

  echo "" >> "$REPORT"
  echo "---" >> "$REPORT"
  echo "" >> "$REPORT"
done

# 写入总结
echo "## 测试总结" >> "$REPORT"
echo "" >> "$REPORT"
echo "- **通过**: $PASS" >> "$REPORT"
echo "- **失败**: $FAIL" >> "$REPORT"
echo "- **总计**: $((PASS + FAIL))" >> "$REPORT"
echo "" >> "$REPORT"

if [ $FAIL -eq 0 ]; then
  echo "**结果**: ✅ 所有接口测试通过" >> "$REPORT"
  echo ""
  echo "✅ 所有接口测试通过！"
else
  echo "**结果**: ❌ 有接口测试失败，请检查上述报告" >> "$REPORT"
  echo ""
  echo "❌ 有 $FAIL 个接口测试失败，请查看 $REPORT"
fi

echo ""
echo "测试完成！报告已保存到: $REPORT"
