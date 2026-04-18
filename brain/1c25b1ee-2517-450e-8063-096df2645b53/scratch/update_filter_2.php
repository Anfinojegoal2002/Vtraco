<?php
$content = file_get_contents('c:\\xampp\\htdocs\\vtraco\\src\\views\\admin.php');

$search = "                <?php \n                \$filteredEmployees = array_filter(\$allEmployees, function(\$emp) use (\$employeeType) {\n                    \$type = (string) (\$emp['employee_type'] ?? 'regular');\n                    if (\$employeeType === 'regular') {\n                        return \$type === 'regular' || \$type === '';\n                    }\n                    return \$type === \$employeeType;\n                });\n                foreach (\$filteredEmployees as \$employee):";

$replace = "                <?php \n                \$filteredEmployees = array_filter(\$allEmployees, function(\$emp) use (\$employeeType) {\n                    \$type = (string) (\$emp['employee_type'] ?? 'regular');\n                    if (\$employeeType === 'regular') {\n                        return \$type === 'regular' || \$type === '';\n                    }\n                    if (\$employeeType === 'vendor') {\n                        return \$type === 'vendor' || \$type === 'regular' || \$type === '';\n                    }\n                    return \$type === \$employeeType;\n                });\n                if (\$employeeType === 'vendor') {\n                    usort(\$filteredEmployees, function(\$a, \$b) {\n                        \$typeA = (string) (\$a['employee_type'] ?? 'regular');\n                        \$typeB = (string) (\$b['employee_type'] ?? 'regular');\n                        if (\$typeA === 'vendor' && \$typeB !== 'vendor') return -1;\n                        if (\$typeB === 'vendor' && \$typeA !== 'vendor') return 1;\n                        return \$b['id'] <=> \$a['id'];\n                    });\n                }\n                foreach (\$filteredEmployees as \$employee):";

$content = str_replace(str_replace("\r\n", "\n", $search), str_replace("\r\n", "\n", $replace), str_replace("\r\n", "\n", $content));

file_put_contents('c:\\xampp\\htdocs\\vtraco\\src\\views\\admin.php', $content);
echo "Done";
