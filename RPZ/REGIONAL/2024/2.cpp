#include <iostream>
#include <vector>
#include <algorithm>

int main()
{
    int T;
    std::cin >> T;
    while(T > 0)
    {
        T--;
        int n;
        std::cin >> n;
        std::vector<long long> AA(n);
        unsigned long long sum = 0;
        for(int i = 0; i < n; i++) 
        {
            std::cin >> AA[i];
            sum+=AA.at(i);
        }
        std::sort(AA.begin(), AA.end());
        if(n < 3)
        {
            std::cout << -1 << std::endl;
            continue;
        }
        bool f = false;
        while(AA.size() > 2)
        {
            if(AA.at(AA.size()-1) >= sum - AA.at(AA.size()-1))
            {
                sum -= AA.at(AA.size()-1);
                AA.pop_back();
            } 
            else
            {
                std::cout << sum << std::endl;
                f = true;
                break;
            }
        }
        if(!f)
            std::cout << -1 << std::endl;
    }
    return 0;
}