#include <iostream>

unsigned long long mod(unsigned long long x, unsigned long long a, unsigned long long c)
{
    if(a < 2) return x;
    unsigned long long t = mod(x, a/2, c) % c, y = (t * t) % c;
    if(a % 2 == 0)
        return y; 
    return (y * x) % c;
}

int main()
{
    int a, b, c;
    std::cin >> a >> b >> c;
    int m = b % c;
    int x = 1;
    while(true)
    {
        if(mod(x, a, c) == m) break;
        x++;
    }
    std::cout << x << std::endl;
    return 0;
}