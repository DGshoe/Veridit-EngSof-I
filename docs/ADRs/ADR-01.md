# ADR 1 ~ Estilo arquitetural: Arquitetura baseada em serviços
| Status    | DATA |
| -------- | ------- |
| Aceita | 20/10/26 |


### Contexto
Precisávamos definir uma arquitetura geral para o sistema, um sistema jurídico web, com diversas funcionalidades/serviços diferentes.




### Decisão
Escolhemos a arquitetura baseada em serviços (Richards & Ford, Cap. 13) dentre as várias possíveis.




### Justificativa
Essa arquitetura foi escolhida pelo nosso grupo devido a pluralidade de linguagens e tecnologias conhecida pelos membros, assim sendo necessário separar os requisitos em times de desenvolvimento distintos.




### Consequências
- (+) Grupos independentes, cada um com seus próprios serviços, e implantação separada por serviço.
- (+) A captura pode ser escalada sem escalar o resto do sistema.
- (+) A falha de um serviço não derruba todos. Por exemplo, se o serviço de relatórios cair, o login continua funcionando.
- (−) Surgem chamadas de rede entre serviços, com latência e falhas parciais.
- (−) O banco compartilhado cria acoplamento de esquema entre serviços.
- (−) Há mais containers para operar, e a autenticação passa a ser distribuída.
