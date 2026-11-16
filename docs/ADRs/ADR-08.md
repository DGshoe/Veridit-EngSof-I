# ADR-8 ~ Envio de e-mails centralizado no Serviço
| Status    | DATA |
| -------- | ------- |
| Aceita | 20/10/26 |


## Contexto:
É preciso uma forma de recuperação de senha, avisos entre outros serviços que exigem comunicação direta com usuarios.


### Decisão:
O Serviço de Identidade oferece um endpoint interno que recebe o tipo de mensagem e os dados. A mensagem é enfileirada (Laravel Queues sobre Redis) e enviada pelo Laravel Mail por um provedor SMTP transacional, com templates no mesmo estilo das telas. Os e-mails não levam anexos nem links públicos para download, apenas um link para entrar no sistema.






### Justificativa
Credenciais SMTP, templates e histórico de envios ficam num só lugar, e a Identidade já precisa enviar e-mails de senha. A fila impede que a lentidão do provedor de e-mail atrase uma compra ou uma captura, o que favorece a confiabilidade. Mandar e-mails sem anexos protege as evidências, o que favorece a segurança.




### Consequências
- (+) E-mails consistentes no visual e na configuração.
- (−) Registros e Créditos dependem da Identidade para notificar. Se ela estiver fora do ar, o serviço que chamou tenta de novo mais tarde.
