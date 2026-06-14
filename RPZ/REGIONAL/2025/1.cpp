#include <iostream>
#include <string>

int move(char A[1000][1000], int N, int M, char pos, int &posX, int &posY)
{
	int coin = 0;
	bool r, l, u, d;
	r = u = l = d = false;
	switch(pos)
	{
		case 'W':
			u = true;
			break;
		case 'S':
			d = true;
			break;
		case 'D':
			r = true;
			break;
		case 'A':
			l = true;
			break;
	}
	bool moved = false;
	int oldY = posY, oldX = posX;
	posY = posY-u+d;
	posX = posX + r - l;
	if(posY == N)
	{
		posY = 0;
		moved = true;
	}
	if(posX == M)
	{
		posX = 0;
		moved = true;
	}
	if(posY == -1)
	{
		posY = N-1;
		moved = true;
	}
	if(posX == -1)
	{
		posX = M-1;
		moved = true;
	}
	char p = A[posY][posX];
	if(p != '#')
	{
		if(p == '*')
		{
			coin++;
			A[posY][posX] = '.';
		}
	}
	else
	{
		posX = oldX;
		posY = oldY;
	}	
	return coin;	
}

int main()
{
	std::string POS;
    int N, M, posX, posY, c = 0;
    std::cin >> N >> M;
	char A[1000][1000];
    for(int i = 0; i < N; i ++)
    {
    	for(int j = 0; j < M; j++)
    	{
    		std::cin >> A[i][j];
			if(A[i][j] == 'S')
			{
				posX = j;
				posY = i;
			}
		}
	}
	std::cin >> POS;	
	for(int i = 0; i < POS.size(); i++)
		c += move(A, N, M, POS[i], posX, posY);
	std::cout << c << std::endl;
    return 0;
}
