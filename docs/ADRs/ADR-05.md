# ADR 5 ~ Tokens JWT assinados para autorização entre serviços
| Status    | DATA |
| -------- | ------- |
| Aceita | 20/10/26 |




## Contexto:
É necessario saber quem está acessando ou tentando acessar o nosso serviço para distribuir as credenciais necessarias.








### Decisão:
Utilizar tokens JWT para manejar autorizações entre serviços.


### Justificativa
Além de ser fácil de lidar e haver inúmeras bibliotecas open-source que nos permitem lidar com tokens JWT ainda temos o benefício de ser leve e simples, além de, em maior parte, ser seguro quando configurado com cautela.




### Consequências
- (+) Autenticação distribuída simples, sem chamada extra por requisição.
- (−) É preciso gerenciar e trocar periodicamente as chaves de assinatura.
- (−) Um token roubado vale por até 15 minutos; o risco é reduzido pela lista de bloqueio e pelo uso obrigatório de HTTPS.
