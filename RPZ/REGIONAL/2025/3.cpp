#include <iostream>
#include <vector>
#include <algorithm>

int main()
{
    int N;
    std::cin >> N;
    std::vector<long long> n(N, 0);
    std::vector<int> T(N);
    std::vector<int> P(N - 1);
    for (int i = 0; i < N; std::cin >> T[i++]); 
    for (int i = 0; i < N - 1; std::cin >> P[i++]);
    if(N <2) 
    {
        std::cout << T[0] << std::endl;
        return 0;
    }
    n[0] = T[0];
    n[1] = std::min(T[0] + T[1], P[0]);
    for (int i = 2; i < N; i++)
        n[i] = std::min(n[i - 1] + T[i], n[i - 2] + P[i - 1]);
    std::cout << n[N - 1] << std::endl;
    return 0;
}
