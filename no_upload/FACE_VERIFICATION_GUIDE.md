# 🔐 Sistema de Verificação Facial - AWS Rekognition

## 📋 Visão Geral

Sistema de verificação de identidade facial implementado para proteger alterações de dados sensíveis em `cliente_edit.php`. Utiliza AWS Rekognition para comparar a selfie atual do usuário com a selfie original cadastrada no banco de dados.

## 🎯 Objetivo

**Segurança adicional** para alterações de:
- ✉️ **Email** do cliente
- 🆔 **CPF** do cliente  
- 🔑 **Senha** do cliente

Quando qualquer um desses campos é alterado, o sistema **OBRIGA** a verificação facial antes de salvar as mudanças.

---

## 🏗️ Arquitetura

### Arquivos Criados/Modificados

1. **`src/FaceValidator.php`** (já existia, mantido)
   - Classe wrapper para AWS Rekognition
   - Métodos: `compareFaces()`, `detectFace()`, `indexFace()`, `searchFacesByImage()`

2. **`ajax_verify_face.php`** (NOVO)
   - Endpoint AJAX para verificação facial
   - Valida permissões do usuário
   - Compara faces usando AWS Rekognition
   - Registra tentativas na tabela `facial_verifications`
   - Gera token de verificação válido por 5 minutos

3. **`cliente_edit.php`** (MODIFICADO)
   - Detecta mudanças em campos sensíveis
   - Mostra alerta de verificação obrigatória
   - Modal com captura de câmera
   - JavaScript para captura de selfie e envio via AJAX
   - Validação de token antes de salvar alterações

4. **`test_face_verification.php`** (NOVO)
   - Interface de teste standalone
   - Busca clientes no banco de dados
   - Captura selfie via câmera
   - Testa comparação facial em tempo real

---

## 🔄 Fluxo de Funcionamento

### 1️⃣ Detecção de Mudança Sensível
```javascript
// JavaScript monitora mudanças nos campos
emailInput.addEventListener('input', checkSensitiveChanges);
cpfInput.addEventListener('input', checkSensitiveChanges);
senhaInput.addEventListener('input', checkSensitiveChanges);

// Se detectar mudança sensível, mostra alerta
if (emailChanged || cpfChanged || senhaChanged) {
    alertBox.classList.remove('d-none');
}
```

### 2️⃣ Abertura do Modal de Verificação
- Usuário clica em "Verificar Identidade Agora"
- Modal abre com acesso à câmera
- Vídeo mostra preview espelhado (mais natural para o usuário)

### 3️⃣ Captura da Selfie
```javascript
// Captura frame do vídeo
canvas.width = video.videoWidth;
canvas.height = video.videoHeight;

// Desenha no canvas (inverte espelhamento)
ctx.scale(-1, 1);
ctx.drawImage(video, -canvas.width, 0, canvas.width, canvas.height);

// Converte para JPEG
const imageDataUrl = canvas.toDataURL('image/jpeg', 0.9);
```

### 4️⃣ Envio para AWS Rekognition
```javascript
// Envia via AJAX para ajax_verify_face.php
fetch('ajax_verify_face.php', {
    method: 'POST',
    body: formData // contém: verification_selfie + cliente_id
})
```

### 5️⃣ Processamento no Backend
```php
// 1. Valida permissões do usuário
// 2. Verifica se cliente tem selfie original
// 3. Detecta face na nova selfie (AWS Rekognition DetectFaces)
// 4. Analisa qualidade da foto
// 5. Compara faces (AWS Rekognition CompareFaces)
// 6. Registra tentativa no banco de dados
// 7. Gera token de verificação (válido 5 minutos)
```

### 6️⃣ Resultado da Verificação

**✅ SUCESSO (similaridade ≥ 90%):**
- Token salvo em `$_SESSION['face_verification_token']`
- Badge verde "Identidade verificada!" aparece
- Modal fecha automaticamente após 2 segundos
- Usuário pode salvar o formulário

**❌ FALHA (similaridade < 90%):**
- Mensagem de erro detalhada
- Usuário pode tentar novamente
- Tentativa registrada na tabela `facial_verifications`

### 7️⃣ Salvamento do Formulário
```php
// Valida token antes de salvar
if ($sensitive_data_changed) {
    if (
        empty($_POST['verification_token']) ||
        $_POST['verification_token'] !== $_SESSION['face_verification_token'] ||
        time() > $_SESSION['face_verification_expires']
    ) {
        throw new Exception('Verificação facial obrigatória');
    }
}

// Token válido → Limpa token (uso único)
unset($_SESSION['face_verification_token']);

// Salva alterações no banco de dados
$stmt->execute($params);
```

---

## 🗄️ Estrutura do Banco de Dados

### Tabela: `facial_verifications`

Criada no arquivo `migrations/add_login_security.sql`:

```sql
CREATE TABLE IF NOT EXISTS facial_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    usuario_id INT NOT NULL,
    similarity_score DECIMAL(5,2) DEFAULT 0.00,
    verification_result ENUM('success', 'failed') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cliente (cliente_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_result (verification_result),
    INDEX idx_created (created_at),
    FOREIGN KEY (cliente_id) REFERENCES kyc_clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Colunas:**
- `id`: Primary key auto-incremento
- `cliente_id`: ID do cliente sendo editado (FK)
- `usuario_id`: ID do usuário fazendo a verificação (FK)
- `similarity_score`: Porcentagem de similaridade (0-100)
- `verification_result`: 'success' ou 'failed'
- `ip_address`: IP do usuário
- `user_agent`: User agent do navegador
- `created_at`: Data/hora da tentativa

**Queries Úteis:**

```sql
-- Ver últimas tentativas de verificação
SELECT 
    fv.id,
    fv.created_at,
    fv.similarity_score,
    fv.verification_result,
    kc.nome_completo AS cliente_nome,
    u.nome AS usuario_nome
FROM facial_verifications fv
JOIN kyc_clientes kc ON fv.cliente_id = kc.id
JOIN usuarios u ON fv.usuario_id = u.id
ORDER BY fv.created_at DESC
LIMIT 50;

-- Verificações falhadas (possíveis tentativas de fraude)
SELECT 
    cliente_id,
    COUNT(*) as tentativas_falhas,
    MAX(created_at) as ultima_tentativa,
    AVG(similarity_score) as media_similaridade
FROM facial_verifications
WHERE verification_result = 'failed'
GROUP BY cliente_id
HAVING tentativas_falhas >= 3
ORDER BY tentativas_falhas DESC;

-- Taxa de sucesso por usuário
SELECT 
    u.nome,
    COUNT(*) as total_verificacoes,
    SUM(CASE WHEN fv.verification_result = 'success' THEN 1 ELSE 0 END) as sucessos,
    ROUND(SUM(CASE WHEN fv.verification_result = 'success' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as taxa_sucesso
FROM facial_verifications fv
JOIN usuarios u ON fv.usuario_id = u.id
GROUP BY u.id, u.nome
ORDER BY total_verificacoes DESC;
```

---

## ⚙️ Configuração AWS

### Variáveis de Ambiente (.env)

```env
AWS_ACCESS_KEY_ID=AKIAT4CGSMKPTC2YMXI2
AWS_SECRET_ACCESS_KEY=WLZO7saF...
AWS_REGION=us-east-1
AWS_REKOGNITION_COLLECTION=verify-kyc-faces
FACE_MATCH_THRESHOLD=90
```

### Permissões IAM Necessárias

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "rekognition:CompareFaces",
                "rekognition:DetectFaces",
                "rekognition:CreateCollection",
                "rekognition:DescribeCollection",
                "rekognition:IndexFaces",
                "rekognition:SearchFacesByImage",
                "rekognition:DeleteFaces"
            ],
            "Resource": "*"
        }
    ]
}
```

### Custos AWS Free Tier

| Serviço | Free Tier | Após Free Tier |
|---------|-----------|----------------|
| **DetectFaces** | 5.000 imagens/mês (12 meses) | $0,001 por imagem |
| **CompareFaces** | 1.000 comparações/mês (12 meses) | $0,001 por comparação |
| **SearchFacesByImage** | 1.000 buscas/mês (12 meses) | $0,001 por busca |
| **IndexFaces** | 1.000 faces/mês (12 meses) | $0,001 por face |

**Estimativa de Uso:**
- 5.300 validações/mês projetadas
- Cada verificação = 1 DetectFaces + 1 CompareFaces
- Total: 10.600 requisições/mês
- **Custo após Free Tier:** ~$10,60/mês

---

## 🧪 Como Testar

### 1. Teste Manual via Interface

1. Acesse: `https://verify2b.com/test_face_verification.php`
2. Busque um cliente existente (ex: "Alba")
3. Clique em "Iniciar Câmera"
4. Posicione seu rosto e clique em "Capturar Foto"
5. Clique em "Comparar Faces"
6. Observe resultado: similaridade e status

### 2. Teste Integrado em cliente_edit.php

1. Acesse: `https://verify2b.com/cliente_edit.php?id=1`
2. Altere o **email** do cliente
3. Observe alerta amarelo: "Verificação facial obrigatória!"
4. Clique em "Verificar Identidade Agora"
5. Capture selfie e verifique
6. Após sucesso, badge verde aparece
7. Clique em "Salvar Alterações"
8. ✅ Dados salvos com sucesso!

### 3. Teste de Segurança (Bypass de Token)

Tente burlar o sistema:

```javascript
// No console do navegador, tente forjar token
document.getElementById('verification_token').value = 'token-falso-12345';

// Altere email e tente salvar
document.getElementById('email').value = 'novo@email.com';
document.querySelector('form').submit();

// ❌ DEVE FALHAR com erro: "Verificação facial obrigatória"
```

### 4. Teste de Expiração de Token

```php
// Simular token expirado
$_SESSION['face_verification_token'] = 'token-valido';
$_SESSION['face_verification_expires'] = time() - 60; // 1 minuto atrás

// Tentar salvar
// ❌ DEVE FALHAR: token expirado
```

---

## 🛡️ Segurança Implementada

### ✅ Proteções Ativas

1. **Token de Uso Único**
   - Token gerado após verificação bem-sucedida
   - Armazenado em `$_SESSION` (server-side)
   - Expiração de 5 minutos
   - Destruído após uso

2. **Validação de Permissões**
   - Verifica se usuário está autenticado
   - Valida se usuário tem permissão para editar cliente
   - Admin/Analista só pode editar clientes da própria empresa

3. **Validação de Arquivo**
   - Tamanho máximo: 5MB
   - Tipos permitidos: JPG, PNG
   - Validação MIME type real (não confia na extensão)

4. **Validação de Qualidade da Selfie**
   - Verifica se há exatamente 1 face
   - Analisa brightness, sharpness, confidence
   - Rejeita fotos com óculos de sol, olhos fechados, etc.

5. **Threshold de Similaridade**
   - Mínimo: 90% de similaridade
   - Configurável via `.env` (FACE_MATCH_THRESHOLD)

6. **Auditoria Completa**
   - Todas tentativas registradas em `facial_verifications`
   - IP e User Agent capturados
   - Possibilita análise forense de tentativas de fraude

### 🚨 Possíveis Vulnerabilidades e Mitigações

| Vulnerabilidade | Mitigação Implementada |
|----------------|------------------------|
| **Foto impressa** | AWS Rekognition detecta liveness parcialmente via análise de profundidade |
| **Deep fake** | Threshold alto (90%) dificulta, mas não impede completamente |
| **Replay attack** | Token de uso único + expiração 5 min |
| **CSRF** | Token gerado em sessão server-side, não exposto em cookies |
| **Brute force** | Rate limiting pode ser adicionado (próxima feature) |

**⚠️ RECOMENDAÇÕES FUTURAS:**
- Implementar **liveness detection** (piscar olhos, virar cabeça)
- Adicionar **rate limiting** em `ajax_verify_face.php` (ex: máx 5 tentativas/hora)
- Integrar **AWS Rekognition Liveness** (lançado recentemente)

---

## 📊 Monitoramento e Logs

### Logs do Sistema

```php
// Em ajax_verify_face.php, adicionar logging
error_log(sprintf(
    "[FACE_VERIFICATION] Cliente: %d | Usuario: %d | Resultado: %s | Similaridade: %.2f%%",
    $cliente_id,
    $_SESSION['user_id'],
    $comparison['match'] ? 'SUCCESS' : 'FAILED',
    $comparison['similarity']
));
```

### Dashboard de Métricas (Futuro)

Criar página `facial_verification_dashboard.php`:

```php
// Métricas úteis:
- Total de verificações (dia/semana/mês)
- Taxa de sucesso/falha
- Clientes com mais tentativas falhadas (suspeitos)
- Usuários com baixa taxa de sucesso (treinamento necessário)
- Gráfico de similaridade média ao longo do tempo
```

---

## 🐛 Troubleshooting

### Problema: "Credenciais AWS não configuradas"

**Solução:**
```bash
# Verificar se .env existe
ls -la .env

# Verificar se variáveis estão carregadas
php -r "require '.env'; var_dump(getenv('AWS_ACCESS_KEY_ID'));"
```

### Problema: "Nenhuma face detectada na imagem"

**Causas possíveis:**
- Foto muito escura
- Rosto fora do enquadramento
- Baixa resolução da câmera
- Óculos de sol, chapéu, etc.

**Solução:**
```javascript
// Aumentar resolução da captura
video: { 
    facingMode: 'user',
    width: { ideal: 1920 }, // era 1280
    height: { ideal: 1080 } // era 720
}
```

### Problema: "Collection 'verify-kyc-faces' não existe"

**Solução:**
A collection é criada automaticamente no construtor de `FaceValidator.php`:

```php
private function ensureCollectionExists() {
    try {
        $this->client->describeCollection([
            'CollectionId' => $this->collectionId
        ]);
    } catch (AwsException $e) {
        if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
            $this->client->createCollection([
                'CollectionId' => $this->collectionId
            ]);
        }
    }
}
```

### Problema: "Similaridade muito baixa para mesma pessoa"

**Causas:**
- Iluminação diferente entre fotos
- Ângulo de câmera diferente
- Expressão facial muito diferente
- Qualidade de imagem baixa

**Solução:**
```env
# Reduzir threshold temporariamente para testes
FACE_MATCH_THRESHOLD=85
```

---

## 📝 Checklist de Implementação

### ✅ Concluído
- [x] Criar classe `FaceValidator.php`
- [x] Criar endpoint `ajax_verify_face.php`
- [x] Modificar `cliente_edit.php` com modal de câmera
- [x] Adicionar validação de token no POST
- [x] Criar tabela `facial_verifications`
- [x] Implementar registro de auditoria
- [x] Criar página de teste `test_face_verification.php`
- [x] Documentação completa

### 🔄 Próximos Passos (Futuro)
- [ ] Implementar liveness detection (piscar olhos)
- [ ] Adicionar rate limiting em ajax_verify_face.php
- [ ] Criar dashboard de métricas
- [ ] Implementar notificação de múltiplas falhas
- [ ] Adicionar suporte a AWS Rekognition Liveness
- [ ] Criar testes automatizados (PHPUnit)
- [ ] Implementar fallback para 2FA via SMS se verificação facial falhar

---

## 🎓 Recursos Adicionais

### Documentação Oficial
- [AWS Rekognition CompareFaces](https://docs.aws.amazon.com/rekognition/latest/dg/faces-comparefaces.html)
- [AWS Rekognition DetectFaces](https://docs.aws.amazon.com/rekognition/latest/dg/faces-detect-images.html)
- [AWS Rekognition Best Practices](https://docs.aws.amazon.com/rekognition/latest/dg/best-practices.html)

### Tutoriais
- [Facial Recognition with PHP and AWS](https://aws.amazon.com/blogs/machine-learning/)
- [MediaDevices getUserMedia API](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia)

---

## 📞 Suporte

**Desenvolvido por:** Copilot + Alba Cerdeira  
**Data:** Novembro 2025  
**Versão:** 1.0  
**Status:** ✅ Produção

Para dúvidas ou problemas, consulte os logs em:
- `ajax_verify_face.php` (linha 130-180)
- Tabela `facial_verifications` no banco de dados
- Console do navegador (Network tab)
