# ADR-7 ~ Integridade das evidências com hash SHA-256
| Status    | DATA |
| -------- | ------- |
| Aceita | 20/10/26 |








## Contexto:
É preciso uma forma de garantir a integridade das evidências.




### Decisão:
Para isso, utilizaremos o algoritimo de HASH SHA-256.




### Justificativa
É leve, fácil e altamente confiável. Por ser um algoritmo HASH provado na prática, não precisamos nos preocupar com falsificações. Além disso, o SHA-256 é leve e pouco custoso computacionalmente.








### Consequências
- (+) A integridade pode ser verificada de forma independente, e os registros servem de base para auditoria.
- (−) Calcular o hash de vídeos grandes consome processamento; o cálculo é feito por partes, enquanto o arquivo é lido.
