<!DOCTYPE html> 
<html> 
<body> 

<h1>My first PHP page</h1> 

<h3>hello world</h3>

<?php 
    echo "Hello World!"; 
?> 

<?php
    $a = "hello world";
    echo $a;
?>

<?php
    $b = "true";
    $c = "false";
    if(!true){
        echo $b;
    }else{
        echo $c;
    }
?>

<form action="welcome.php" method="post">
名字: <input type="text" name="fname">
年龄: <input type="text" name="age">
<input type="submit" value="提交">
</form>


</body> 
</html>