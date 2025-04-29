#!/bin/bash
# Script to output README.md and all files in /src/ and /tests/ to all.php.txt
# Skips files that start with underscore (_)

# Change to the project root directory
cd "$(dirname "$0")"

# Create or clear the output file
> all.php.txt

echo "You are an expert code optimizer and famous for making world class libraries in composer that go viral and everyone wants to use, and everyone loves them.  You create beautiful code. \n\n\n" >> all.php.txt

# Add README.md at the beginning
if [ -f README.md ]; then
  echo "===================================================" >> all.php.txt
  echo "FILE: README.md" >> all.php.txt
  echo "===================================================" >> all.php.txt
  echo "" >> all.php.txt
  cat README.md >> all.php.txt
  echo "" >> all.php.txt
  echo "" >> all.php.txt
else
  echo "README.md not found, skipping..."
fi

# Loop through all PHP files in the src directory
for file in $(find ./src -name "*.php" | sort); do
  # Skip files that start with underscore
  filename=$(basename "$file")
  if [[ $filename == _* ]]; then
    echo "Skipping $file (starts with underscore)"
    continue
  fi

  # Add file header
  echo "===================================================" >> all.php.txt
  echo "FILE: $file" >> all.php.txt
  echo "===================================================" >> all.php.txt
  echo "" >> all.php.txt

  # Add file content
  cat "$file" >> all.php.txt

  # Add newlines for separation
  echo "" >> all.php.txt
  echo "" >> all.php.txt
done

# Loop through all PHP files in the tests directory
for file in $(find ./tests -name "*.php" | sort); do
  # Skip files that start with underscore
  filename=$(basename "$file")
  if [[ $filename == _* ]]; then
    echo "Skipping $file (starts with underscore)"
    continue
  fi

  # Add file header
  echo "===================================================" >> all.php.txt
  echo "FILE: $file" >> all.php.txt
  echo "===================================================" >> all.php.txt
  echo "" >> all.php.txt

  # Add file content
  cat "$file" >> all.php.txt

  # Add newlines for separation
  echo "" >> all.php.txt
  echo "" >> all.php.txt
done

echo "README.md and all source and test files (excluding those starting with _) have been output to all.php.txt"
