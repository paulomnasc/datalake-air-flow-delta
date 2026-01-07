/**
 * Git Loader Wrapper
 * Importa isomorphic-git + http/web como ESM
 * LightningFS é carregado como UMD antes deste módulo
 */

// Importar os módulos ESM (apenas isomorphic-git, sem lightning-fs)
import gitModule from './isomorphic-git/index.js';
import httpModule from './isomorphic-git/http-web.js';

// Expor globalmente
window.git = gitModule;
window.git.http = httpModule;

// LightningFS já deve estar disponível via UMD
if (!window.LightningFS) {
    console.error('❌ LightningFS não encontrado! Deve ser carregado via UMD antes.');
}

// Sinalizar que foi carregado
window.__gitModulesReady = true;
console.log('✓ Git modules (ESM wrapper) carregados');
console.log('window.git:', window.git);
console.log('window.LightningFS:', window.LightningFS);

