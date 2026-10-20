# ADR 2 ~ Metodologia de autenticação do sistema
Título: Metodologia de Autenticação do sistema
| Status    | DATA |
| -------- | ------- |
| Aceita | 20/10/26 |


### Contexto:
Esta ADR engloba os requisitos do sistema que referencia a todo o ciclo de autenticação do usuário: criar usuário, recuperar senha e fazer login.






### Decisão:
A implementação será a partir do framework Laravel da linguagem de programação PHP.


### Justificativa
A escolha foi feita tanto pela implementação que o framework nativamente oferece para autenticação de usuário além da familiaridade que um dos desenvolvedores têm com o framework.




### Consequências
- (+) Agilidade no desenvolvimento
- (+) Facilidade de manutenção
- (-) Maior número de dependências (natural do uso de frameworks)
