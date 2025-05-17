# Sistema de Armazenamento de Arquivos

Um sistema completo de armazenamento de arquivos com interface web, desenvolvido para uso local através do XAMPP.

## Características

- Upload de arquivos (vídeos, imagens e PDFs)
- Visualização de arquivos diretamente no navegador
- Limite de 999 GB de armazenamento total por usuário
- Limite de 29 GB por arquivo
- Listagem e organização dos arquivos enviados
- Opção para excluir arquivos
- Interface com tema marrom, simples e intuitiva

## Requisitos

- XAMPP (ou outro servidor local com suporte a PHP e SQLite)
- Navegador moderno (Chrome, Firefox, Edge, Safari)

## Configuração

1. Clone ou baixe este repositório para a pasta `htdocs` do seu XAMPP (ou equivalente)
2. Inicie o Apache no painel de controle do XAMPP
3. Acesse o sistema pelo navegador em `http://localhost/nome-da-pasta`

## Estrutura de Arquivos

```
├── index.html             # Página principal
├── css/                   # Arquivos de estilo
│   └── style.css          # Estilo principal (tema marrom)
├── js/                    # Scripts JavaScript
│   └── main.js            # Funcionalidades do front-end
├── backend/               # Scripts PHP do backend
│   ├── config.php         # Configurações e inicialização do banco
│   ├── upload.php         # Processamento de uploads
│   ├── list.php           # Listagem de arquivos
│   └── delete.php         # Exclusão de arquivos
├── uploads/               # Pasta onde os arquivos são armazenados
└── database/              # Banco de dados SQLite
```

## Permissões de Arquivos

Para garantir o correto funcionamento do sistema, as pastas `uploads` e `database` precisam ter permissões de escrita:

```bash
chmod 777 uploads database
```

## Customização

### Limites de Armazenamento

Os limites de armazenamento podem ser alterados em:

- `backend/config.php` (para o backend)
- `js/main.js` (para o frontend)

### Tema Visual

O tema visual pode ser alterado editando as variáveis de cores no arquivo `css/style.css`.

## Resolução de Problemas

### Problemas de Upload

- Verifique se as pastas `uploads` e `database` têm permissões de escrita
- Verifique o limite de tamanho de upload no php.ini do XAMPP
- Ajuste `upload_max_filesize` e `post_max_size` no php.ini para permitir uploads maiores

### Visualização de Arquivos

- Certifique-se de que o tipo MIME está corretamente configurado no servidor
- Para arquivos PDF, verifique se o navegador tem suporte a visualização de PDF

## Limitações

- O sistema não possui autenticação de usuários
- Não há verificação de tipos de arquivos maliciosos
- Em produção, seria recomendável implementar medidas adicionais de segurança

## Tecnologias Utilizadas

- HTML5, CSS3 e JavaScript puro (frontend)
- PHP (backend)
- SQLite (banco de dados) 