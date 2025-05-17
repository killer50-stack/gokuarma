// Constantes
const MAX_STORAGE_GB = 999; // 999 GB máximo por usuário
const MAX_FILE_SIZE_GB = 29; // 29 GB por arquivo
const GB_TO_BYTES = 1073741824; // 1 GB em bytes

// Elementos DOM
const fileInput = document.getElementById('file-input');
const fileName = document.getElementById('file-name');
const uploadForm = document.getElementById('upload-form');
const uploadBtn = document.getElementById('upload-btn');
const uploadProgressContainer = document.getElementById('upload-progress-container');
const uploadProgress = document.getElementById('upload-progress');
const uploadStatus = document.getElementById('upload-status');
const fileList = document.getElementById('file-list');
const filterBtns = document.querySelectorAll('.filter-btn');
const storageProgress = document.getElementById('storage-progress');
const storageUsed = document.getElementById('storage-used');
const viewerModal = document.getElementById('viewer-modal');
const viewerTitle = document.getElementById('viewer-title');
const viewerContent = document.getElementById('viewer-content');
const closeModal = document.querySelector('.close-modal');

// Estado da aplicação
let currentFilter = 'all';
let usedStorageBytes = 0;
let files = [];

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    loadFiles();
    setupEventListeners();
});

// Configurar event listeners
function setupEventListeners() {
    // Evento para mostrar nome do arquivo selecionado
    fileInput.addEventListener('change', updateFileInputLabel);
    
    // Evento para submeter o formulário de upload
    uploadForm.addEventListener('submit', handleFileUpload);
    
    // Eventos para os botões de filtro
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.getAttribute('data-filter');
            setActiveFilter(filter);
            loadFiles(filter);
        });
    });
    
    // Fechar modal
    closeModal.addEventListener('click', () => {
        viewerModal.classList.add('hidden');
        viewerContent.innerHTML = '';
    });
    
    // Clicar fora para fechar modal
    viewerModal.addEventListener('click', (e) => {
        if (e.target === viewerModal) {
            viewerModal.classList.add('hidden');
            viewerContent.innerHTML = '';
        }
    });
}

// Atualizar o rótulo de entrada de arquivo após seleção
function updateFileInputLabel() {
    if (fileInput.files.length > 0) {
        const file = fileInput.files[0];
        fileName.textContent = file.name;
        
        // Verificar tamanho do arquivo
        const fileSizeGB = file.size / GB_TO_BYTES;
        if (fileSizeGB > MAX_FILE_SIZE_GB) {
            alert(`O arquivo é muito grande! O tamanho máximo permitido é ${MAX_FILE_SIZE_GB} GB.`);
            fileInput.value = '';
            fileName.textContent = 'Nenhum arquivo selecionado';
        }
    } else {
        fileName.textContent = 'Nenhum arquivo selecionado';
    }
}

// Manipular o upload de arquivo
async function handleFileUpload(event) {
    event.preventDefault();
    
    if (!fileInput.files.length) {
        alert('Por favor, selecione um arquivo para upload.');
        return;
    }
    
    const file = fileInput.files[0];
    
    // Exibir barra de progresso
    uploadProgressContainer.classList.remove('hidden');
    uploadBtn.disabled = true;
    
    // Criar FormData para enviar o arquivo
    const formData = new FormData();
    formData.append('file', file);
    
    try {
        // Enviar o arquivo para o servidor
        const xhr = new XMLHttpRequest();
        
        // Configurar eventos para acompanhar o progresso
        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                const percentComplete = (event.loaded / event.total) * 100;
                uploadProgress.style.width = percentComplete + '%';
                uploadStatus.textContent = `Enviando... ${Math.round(percentComplete)}%`;
            }
        });
        
        // Configurar evento para quando o upload terminar
        xhr.onload = function() {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                
                // Atualizar interface
                loadFiles();
                updateStorageInfo(response.storage.used, response.storage.total);
                
                // Redefinir formulário
                fileInput.value = '';
                fileName.textContent = 'Nenhum arquivo selecionado';
                uploadStatus.textContent = 'Upload concluído!';
                
                // Ocultar barra de progresso depois de 2 segundos
                setTimeout(() => {
                    uploadProgressContainer.classList.add('hidden');
                    uploadBtn.disabled = false;
                    uploadProgress.style.width = '0%';
                    uploadStatus.textContent = 'Enviando...';
                }, 2000);
            } else {
                let errorMsg = 'Erro no upload.';
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    errorMsg = errorResponse.error || errorMsg;
                } catch (e) {}
                
                alert(errorMsg);
                console.error('Erro no upload:', xhr.status, xhr.statusText);
                uploadProgressContainer.classList.add('hidden');
                uploadBtn.disabled = false;
            }
        };
        
        // Configurar evento para erros de rede
        xhr.onerror = function() {
            alert('Erro de conexão durante o upload. Verifique sua conexão com a internet.');
            console.error('Erro de rede no upload');
            uploadProgressContainer.classList.add('hidden');
            uploadBtn.disabled = false;
        };
        
        // Enviar a requisição
        xhr.open('POST', 'backend/upload.php', true);
        xhr.send(formData);
        
    } catch (error) {
        console.error('Erro no upload:', error);
        alert('Ocorreu um erro durante o upload. Por favor, tente novamente.');
        uploadProgressContainer.classList.add('hidden');
        uploadBtn.disabled = false;
    }
}

// Carregar arquivos do servidor
function loadFiles(filter = currentFilter) {
    // Exibir indicador de carregamento
    fileList.innerHTML = '<div class="loading">Carregando arquivos...</div>';
    
    // Fazer requisição AJAX para listar arquivos
    const xhr = new XMLHttpRequest();
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                files = response.files;
                
                // Atualizar interface
                renderFiles();
                updateStorageInfo(response.storage.used, response.storage.total);
            } catch (error) {
                console.error('Erro ao processar dados:', error);
                fileList.innerHTML = '<div class="loading">Erro ao carregar arquivos.</div>';
            }
        } else {
            fileList.innerHTML = '<div class="loading">Erro ao carregar arquivos.</div>';
            console.error('Erro na requisição:', xhr.status, xhr.statusText);
        }
    };
    
    xhr.onerror = function() {
        fileList.innerHTML = '<div class="loading">Erro de conexão. Verifique sua internet.</div>';
    };
    
    // Enviar requisição GET
    xhr.open('GET', `backend/list.php?filter=${filter}`, true);
    xhr.send();
}

// Renderizar lista de arquivos com filtro
function renderFiles() {
    fileList.innerHTML = '';
    
    if (files.length === 0) {
        fileList.innerHTML = '<div class="loading">Nenhum arquivo encontrado.</div>';
        return;
    }
    
    // Renderizar cada arquivo
    files.forEach(file => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.dataset.id = file.id;
        
        const thumbnail = createThumbnail(file);
        const fileInfo = createFileInfo(file);
        const fileActions = createFileActions(file);
        
        fileItem.appendChild(thumbnail);
        fileItem.appendChild(fileInfo);
        fileItem.appendChild(fileActions);
        
        fileList.appendChild(fileItem);
    });
}

// Criar thumbnail para arquivo
function createThumbnail(file) {
    const thumbnail = document.createElement('div');
    thumbnail.className = 'file-thumbnail';
    
    if (file.type === 'image') {
        const img = document.createElement('img');
        img.src = file.path;
        img.alt = file.name;
        img.onerror = () => {
            // Fallback caso a imagem não seja encontrada
            img.remove();
            thumbnail.innerHTML = '<div class="file-icon">🖼️</div>';
        };
        thumbnail.appendChild(img);
    } else if (file.type === 'video') {
        thumbnail.innerHTML = '<div class="file-icon">🎬</div>';
    } else if (file.type === 'pdf') {
        thumbnail.innerHTML = '<div class="file-icon">📄</div>';
    } else {
        thumbnail.innerHTML = '<div class="file-icon">📁</div>';
    }
    
    // Adicionar evento de clique para visualizar o arquivo
    thumbnail.addEventListener('click', () => viewFile(file));
    
    return thumbnail;
}

// Criar informações do arquivo
function createFileInfo(file) {
    const fileInfo = document.createElement('div');
    fileInfo.className = 'file-info';
    
    const name = document.createElement('div');
    name.className = 'file-name';
    name.textContent = file.name;
    name.title = file.name;
    
    const meta = document.createElement('div');
    meta.className = 'file-meta';
    
    const size = document.createElement('span');
    size.textContent = formatFileSize(file.size);
    
    const date = document.createElement('span');
    date.textContent = formatDate(file.date);
    
    meta.appendChild(size);
    meta.appendChild(date);
    
    fileInfo.appendChild(name);
    fileInfo.appendChild(meta);
    
    return fileInfo;
}

// Criar botões de ação para o arquivo
function createFileActions(file) {
    const fileActions = document.createElement('div');
    fileActions.className = 'file-actions';
    
    // Botão de visualização
    const viewBtn = document.createElement('button');
    viewBtn.className = 'file-action-btn view-btn';
    viewBtn.innerHTML = '👁️';
    viewBtn.title = 'Visualizar';
    viewBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        viewFile(file);
    });
    
    // Botão de exclusão
    const deleteBtn = document.createElement('button');
    deleteBtn.className = 'file-action-btn delete-btn';
    deleteBtn.innerHTML = '🗑️';
    deleteBtn.title = 'Excluir';
    deleteBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        deleteFile(file.id);
    });
    
    fileActions.appendChild(viewBtn);
    fileActions.appendChild(deleteBtn);
    
    return fileActions;
}

// Visualizar arquivo
function viewFile(file) {
    viewerTitle.textContent = file.name;
    viewerContent.innerHTML = '';
    
    if (file.type === 'image') {
        const img = document.createElement('img');
        img.src = file.path;
        img.alt = file.name;
        img.onerror = () => {
            viewerContent.innerHTML = '<p>Erro ao carregar a imagem. Por favor, tente novamente mais tarde.</p>';
        };
        viewerContent.appendChild(img);
    } else if (file.type === 'video') {
        const video = document.createElement('video');
        video.controls = true;
        video.autoplay = false;
        
        const source = document.createElement('source');
        source.src = file.path;
        source.type = getVideoMimeType(file.name);
        
        video.appendChild(source);
        video.onerror = () => {
            viewerContent.innerHTML = '<p>Erro ao carregar o vídeo. Por favor, tente novamente mais tarde.</p>';
        };
        
        viewerContent.appendChild(video);
    } else if (file.type === 'pdf') {
        // Incorporar PDF usando iframe
        const iframe = document.createElement('iframe');
        iframe.src = file.path;
        iframe.title = file.name;
        iframe.onerror = () => {
            viewerContent.innerHTML = '<p>Erro ao carregar o PDF. Por favor, tente novamente mais tarde.</p>';
        };
        
        viewerContent.appendChild(iframe);
    } else {
        viewerContent.innerHTML = '<p>Tipo de arquivo não suportado para visualização.</p>';
    }
    
    viewerModal.classList.remove('hidden');
}

// Excluir arquivo
function deleteFile(fileId) {
    if (confirm('Tem certeza que deseja excluir este arquivo?')) {
        // Criar FormData para enviar o ID do arquivo
        const formData = new FormData();
        formData.append('id', fileId);
        formData.append('_method', 'DELETE'); // Para simular DELETE em navegadores que não suportam
        
        // Fazer requisição AJAX para excluir o arquivo
        const xhr = new XMLHttpRequest();
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    // Atualizar interface
                    loadFiles(currentFilter);
                    updateStorageInfo(response.storage.used, response.storage.total);
                    
                    // Mostrar mensagem de sucesso
                    alert('Arquivo excluído com sucesso.');
                } catch (error) {
                    console.error('Erro ao processar resposta:', error);
                    alert('Erro ao excluir o arquivo.');
                }
            } else {
                let errorMsg = 'Erro ao excluir o arquivo.';
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    errorMsg = errorResponse.error || errorMsg;
                } catch (e) {}
                
                alert(errorMsg);
                console.error('Erro na exclusão:', xhr.status, xhr.statusText);
            }
        };
        
        xhr.onerror = function() {
            alert('Erro de conexão durante a exclusão. Verifique sua conexão com a internet.');
        };
        
        // Enviar requisição POST (simulando DELETE)
        xhr.open('POST', 'backend/delete.php', true);
        xhr.send(formData);
    }
}

// Atualizar informações de armazenamento
function updateStorageInfo(usedBytes, totalBytes) {
    const usedGB = usedBytes / GB_TO_BYTES;
    const totalGB = totalBytes / GB_TO_BYTES;
    const percentUsed = (usedGB / totalGB) * 100;
    
    storageProgress.style.width = `${percentUsed}%`;
    storageUsed.textContent = usedGB.toFixed(2);
}

// Definir filtro ativo
function setActiveFilter(filter) {
    currentFilter = filter;
    
    // Atualizar classes dos botões de filtro
    filterBtns.forEach(btn => {
        if (btn.getAttribute('data-filter') === filter) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
}

// Formatadores
function formatFileSize(bytes) {
    if (bytes < 1024) {
        return bytes + ' B';
    } else if (bytes < 1048576) {
        return (bytes / 1024).toFixed(1) + ' KB';
    } else if (bytes < 1073741824) {
        return (bytes / 1048576).toFixed(1) + ' MB';
    } else {
        return (bytes / 1073741824).toFixed(2) + ' GB';
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

// Helpers
function getVideoMimeType(filename) {
    const extension = filename.split('.').pop().toLowerCase();
    
    const mimeTypes = {
        'mp4': 'video/mp4',
        'webm': 'video/webm',
        'ogg': 'video/ogg',
        'mov': 'video/quicktime',
        'avi': 'video/x-msvideo'
    };
    
    return mimeTypes[extension] || 'video/mp4';
} 