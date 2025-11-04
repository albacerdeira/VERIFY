# 🚀 GUIA RÁPIDO: Execute Apenas Estes Comandos

## ✅ A coluna `origem` JÁ EXISTE - isso é ótimo!

Execute APENAS estes 3 comandos no phpMyAdmin:

---

### **COMANDO 1** - Adiciona coluna lead_id
```sql
ALTER TABLE kyc_clientes 
ADD COLUMN lead_id INT NULL 
COMMENT 'ID do lead que originou este cliente'
AFTER id_empresa_master;
```

**Se aparecer erro "coluna já existe":** ✅ Ótimo, pule para o comando 2!

---

### **COMANDO 2** - Cria índice
```sql
CREATE INDEX idx_lead_id ON kyc_clientes (lead_id);
```

**Se aparecer erro "índice já existe":** ✅ Ótimo, pule para o comando 3!

---

### **COMANDO 3** - Cria relacionamento com leads
```sql
ALTER TABLE kyc_clientes 
ADD CONSTRAINT fk_kyc_clientes_lead 
FOREIGN KEY (lead_id) REFERENCES leads(id) 
ON DELETE SET NULL;
```

**Se aparecer erro "constraint já existe":** ✅ Perfeito, já está tudo pronto!

---

## 🎯 Como saber se funcionou?

Execute este SELECT:

```sql
SHOW COLUMNS FROM kyc_clientes LIKE 'lead_id';
```

**Resultado esperado:**
```
Field: lead_id
Type: int
Null: YES
Key: MUL
Default: NULL
```

Se aparecer isso ☝️ = **FUNCIONOU!** 🎉

---

## 🧪 TESTE COMPLETO

1. Vá em `leads.php`
2. Clique em "Enviar Formulário" em qualquer lead
3. Copie o link gerado
4. **VERIFIQUE:** O link deve ter `&lead_id=X` no final
5. Abra em aba anônima e complete o registro
6. Após registrar, faça login e veja o dashboard
7. Deve aparecer um alerta azul: "Seu cadastro foi iniciado a partir de um lead..."

---

## ❓ Está dando erro?

**Cole aqui a mensagem de erro exata** que aparece ao executar os comandos.

Erros comuns e suas soluções:
- `#1060 - Nome da coluna 'origem' duplicado` → **IGNORE**, origem já existe
- `#1061 - Nome de índice duplicado` → **IGNORE**, índice já existe  
- `#1826 - Constraint duplicada` → **IGNORE**, constraint já existe
- `#1005 - Cannot add foreign key` → Tabela `leads` pode não existir

---

## ✅ PRONTO!

Após executar os 3 comandos (ignorando erros de "já existe"), o sistema estará **100% funcional**.

Cada novo cliente que se registrar via link de lead será **automaticamente associado** ao lead de origem! 🎯
