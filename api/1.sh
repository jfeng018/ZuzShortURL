#!/bin/bash

OUTPUT_FILE="output.txt"

# 清空输出文件（如果存在）
> "$OUTPUT_FILE"

# 屏蔽的文件和目录
EXCLUDE_FILES=("composer.json" "composer.lock" "output.txt" "1.sh" "favicon.ico" ".htaccess")
EXCLUDE_DIRS=("vendor")

# 遍历当前目录及其子目录
find . -type f | while read -r file; do
    # 获取相对路径
    rel_path="${file#./}"

    # 跳过屏蔽的文件
    skip_file=false
    for excluded in "${EXCLUDE_FILES[@]}"; do
        if [[ "$rel_path" == "$excluded" ]]; then
            skip_file=true
            break
        fi
    done
    [[ "$skip_file" == true ]] && continue

    # 跳过屏蔽的目录
    skip_dir=false
    for excluded in "${EXCLUDE_DIRS[@]}"; do
        if [[ "$rel_path" == "$excluded"* ]] || [[ "$rel_path" == */"$excluded"/* ]]; then
            skip_dir=true
            break
        fi
    done
    [[ "$skip_dir" == true ]] && continue

    # 写入文件名和内容到输出文件
    echo "$rel_path" >> "$OUTPUT_FILE"
    cat "$file" >> "$OUTPUT_FILE"
    echo >> "$OUTPUT_FILE"
done

echo "✅ 所有文件内容已写入 $OUTPUT_FILE"
