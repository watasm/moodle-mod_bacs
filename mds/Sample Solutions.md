# BACS sample solutions

This file contains samples of solving the A+B problem in all programming languages available in the BACS plugin.

### C:

```c
#include <stdio.h>

int main() {
    int a, b;
    scanf("%d %d", &a, &b);
    printf("%d\n", a + b);
    return 0;
}
```

### C++:

```cpp
#include <iostream>
using namespace std;

int main() {
    int a, b;
    cin >> a >> b;
    cout << (a + b) << endl;
    return 0;
}
```

### C# (Mono):

```csharp
using System;

class Program {
    static void Main() {
        var inputs = Console.ReadLine().Split();
        int a = int.Parse(inputs[0]), b = int.Parse(inputs[1]);
        Console.WriteLine(a + b);
    }
}
```

### Delphi:

```delphi
program SumAB;
var
    a, b: Integer;
begin
    ReadLn(a, b);
    WriteLn(a + b);
end.
```

### Pascal:

```pascal
program SumAB;
var
    a, b: Integer;
begin
    ReadLn(a, b);
    WriteLn(a + b);
end.
```

### Python 2:

```python
a, b = map(int, raw_input().split())
print a + b
```

### Python 3:

```python
a, b = map(int, input().split())
print(a + b)
```

### Java 11:

```java
import java.util.Scanner;

public class Main {
    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);
        int a = sc.nextInt();
        int b = sc.nextInt();
        System.out.println(a + b);
    }
}
```

### Java 17:

```java
import java.util.Scanner;

public class Main {
    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);
        int a = sc.nextInt();
        int b = sc.nextInt();
        System.out.println(a + b);
    }
}
```

### C++17:

```cpp
#include <iostream>
using namespace std;

int main() {
    int a, b;
    cin >> a >> b;
    cout << (a + b) << endl;
    return 0;
}
```

### C++20:

```cpp
#include <iostream>
using namespace std;

int main() {
    int a, b;
    cin >> a >> b;
    cout << (a + b) << endl;
    return 0;
}
```

### C# (.NET 6.0):

```csharp
using System;

class Program {
    static void Main() {
        var inputs = Console.ReadLine().Split();
        int a = int.Parse(inputs[0]), b = int.Parse(inputs[1]);
        Console.WriteLine(a + b);
    }
}
```

### Kotlin:

```kotlin
fun main() {
    val (a, b) = readLine()!!.split(" ").map { it.toInt() }
    println(a + b)
}
```

### Golang:

```go
package main

import "fmt"

func main() {
    var a, b int
    fmt.Scan(&a, &b)
    fmt.Println(a + b)
}
```

### Ruby:

```ruby
a, b = gets.split.map(&:to_i)
puts a + b
```

### JavaScript (d8):

```javascript
// These functions are added for input/output
var [a, b] = readline().split(" ").map(Number);
print(a + b);
```

### Rust:

```rust
fn main() {
    let mut input = String::new();
    std::io::stdin().read_line(&mut input).unwrap();
    let nums: Vec<i32> = input
        .split_whitespace()
        .map(|s| s.parse().unwrap())
        .collect();
    let a = nums[0];
    let b = nums[1];
    println!("{}", a + b);
}
```

### PHP 8.1:

```php
<?php
[$a, $b] = explode(' ', trim(fgets(STDIN)));
echo $a + $b;
```

### TypeScript (Bun):

```typescript
const input: string = await Bun.stdin.text();
const [a, b]: number[] = input.trim().split(' ').map(Number);
console.log(a + b);
```

### JavaScript (Bun):

```javascript
const input = await Bun.stdin.text();
const [a, b] = input.trim().split(' ').map(Number);
console.log(a + b);
```