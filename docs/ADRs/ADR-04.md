# ADR 4 ~ Banco de dados: PostgreSQL compartilhado, com um esquema por serviço
| Status    | DATA |
| -------- | ------- |
| Aceita | 20/10/26 |




## Contexto:
Analisamos diversas opções como Firebase, MongoDB e PostgreSQL. Os dados do sistema são estruturados com base em: usuários, compras, lançamentos de crédito, registros e metadados de arquivos.




### Decisão:
Usar uma única instância PostgreSQL com três esquemas: identidade, créditos e registros. Cada serviço escreve só no próprio esquema, com usuário de banco e permissões próprios. O Serviço de Consultas e Relatórios tem acesso apenas de leitura ao esquema registros e lê nome e CPF do usuário por meio de uma view do esquema identidade.




### Justificativa
A situação combina bem com um banco de dados relacional, o postgres também é atualmente um dos databases mais populares no mercado com extenso suporte e atualizações constantes.


### Consequências
- (+) Transações consistentes e consultas de relatório simples.
- (−) Uma mudança no esquema registros afeta o Serviço de Consultas. As views funcionam como contrato entre os dois.
- (−) O banco único é um ponto único de falha, tratado com backups.
