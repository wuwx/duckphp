# Core\SingletonEx
## 简介

**可变单例类**

## 使用于

## 方法

public static function G($object=null)

    如果传入 $object,把当前类设置为 $object。
    如果默认传入空，则创建使用者类。
    
    如果
## 详解

例一
```php
class A
{
    use DuckPhp\Core\SingletonEx;
    public function foo()
    {
        echo "猪年快乐\n";
    }
}
class B extends A
{
    use DuckPhp\Core\SingletonEx;

    public function foo()
    {
        echo "鼠年快乐\n";
    }
}

B::G()->foo();
B::G(A::G());
B::G()->foo();
```
输出
```
猪年快乐
鼠年快乐
```