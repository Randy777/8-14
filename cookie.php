<?php
    $expire=time()+60*60*24*30;
    setcookie("user", "zhangyi", $expire);
    echo "successs";

    echo $_COOKIE["user"];
?>

<!-- <?php
// 输出 cookie 值
echo $_COOKIE["user"];

// 查看所有 cookie
print_r($_COOKIE);
?> -->