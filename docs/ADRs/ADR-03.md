# ADR 3 ~ Características arquiteturais prioritárias (TOP 4)
| Status    | DATA | 
| -------- | ------- |
| Aceita | 20/10/26 |


## Contexto:
Analisando as principais caracteristícas arquiteturais selecionamos 4 das mais relevantes para o sistema.


### Decisão:
Optamos por:
1) Segurança;
2) Integridade dos dados;
3) Confiabilidade em transações;
4) Escalabilidade;


### Justificativa
Após análise decidimos que essas quatro seriam as características principais

Segurança é o único atributo de qualidade explícito no documento, e o sistema guarda CPF, OAB, dados de faturamento e provas que podem ser usadas em processos.

Integridade vem do próprio propósito do produto: se não for possível demonstrar que o arquivo não mudou desde a captura, o registro perde o valor de prova.

Confiabilidade é exigida porque o sistema lida com dinheiro: um pagamento confirmado precisa virar crédito exatamente uma vez, e uma captura que falha não pode consumir créditos nem se perder sem aviso.

Escalabilidade se justifica porque cada captura abre um navegador real e pode gravar vídeo, o que consome muito mais processamento e memória do que as outras operações, e a demanda é irregular.






### Consequências
- (+) As decisões seguintes passam a ser avaliadas por essas quatro características
- (−) A primeira versão aceita que o servidor seja um ponto único de falha.
- (−) Atender essas características exige mais componentes (filas, workers, auditoria), o que aumenta o custo de operação.
