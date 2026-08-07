<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Gateway - Painel Administrativo</title>
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Configuração do Tailwind -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#10B981',
                        danger: '#EF4444',
                        dark: '#1F2937',
                    }
                }
            }
        }
    </script>
    
    <style>
        /* Animações personalizadas */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.3s ease-out;
        }
        
        .animate-slideIn {
            animation: slideIn 0.3s ease-out;
        }
        
        /* Estilo personalizado para inputs */
        input:focus, textarea:focus, select:focus {
            outline: none;
            ring: 2px solid #3B82F6;
        }
        
        /* Modal overlay */
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        /* Notificação toast */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 500px;
            animation: fadeIn 0.3s ease-out;
        }

				/* Barras de progresso animadas */
				.progress-bar {
					transition: width 0.5s ease-in-out;
				}

				/* Sistema de grid para métricas */
				.metrics-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
					gap: 1.5rem;
				}

				/* Animação de pulso para indicador de atualização */
				@keyframes pulse {
					0%, 100% { opacity: 1; }
					50% { opacity: 0.5; }
				}

				.pulse {
					animation: pulse 1s ease-in-out infinite;
				}

				/* Indicador de status */
				.status-indicator {
					width: 8px;
					height: 8px;
					border-radius: 50%;
					display: inline-block;
					margin-right: 6px;
				}

				.status-good { background-color: #10B981; }
				.status-warning { background-color: #F59E0B; }
				.status-critical { background-color: #EF4444; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Toast de notificação -->
    <div id="toast-container"></div>

    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-600 to-blue-700 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-network-wired text-white text-2xl"></i>
                    <h1 class="text-2xl font-bold text-white">API Gateway Manager</h1>
                </div>
                <div id="user-actions"></div>
            </div>
        </div>
    </header>

    <!-- Conteúdo principal -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Tela de Login -->
        <div id="login-screen" class="min-h-[60vh] flex items-center justify-center">
            <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full animate-fadeIn">
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-100 rounded-full mb-4">
                        <i class="fas fa-shield-haltered text-blue-600 text-3xl"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">Autenticação</h2>
                    <p class="text-gray-500">Insira o token de administrador para acessar</p>
                </div>
                
                <form onsubmit="login(event)" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Token de Acesso
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                <i class="fas fa-key text-gray-400"></i>
                            </span>
                            <input 
                                type="password" 
                                id="token-input" 
                                required
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                                placeholder="••••••••••••">
                            <button 
                                type="button"
                                onclick="toggleTokenVisibility()"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                <i id="toggle-icon" class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button 
                        type="submit"
                        class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-3 px-4 rounded-lg font-medium hover:from-blue-700 hover:to-blue-800 transition duration-200 transform hover:scale-[1.02]">
                        <i class="fas fa-sign-in-alt mr-2"></i>Entrar
                    </button>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        Token definido no arquivo admin_token.txt
                    </p>
                </div>
            </div>
        </div>

        <!-- Dashboard -->
        <div id="dashboard" class="hidden">
					<!-- Seção de Monitoramento do Sistema -->
					<div class="mb-8">
						<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-4">
							<div class="flex justify-between items-center">
								<div class="flex items-center space-x-3">
									<h2 class="text-xl font-bold text-gray-800">
										<i class="fas fa-chart-line text-blue-600 mr-2"></i>Monitoramento do Sistema
									</h2>
									<span class="text-xs text-gray-500">
										<span id="update-indicator" class="status-indicator status-good pulse"></span>
										<span id="last-update">Atualizando...</span>
									</span>
								</div>
								<div class="flex items-center space-x-2 text-sm text-gray-500">
									<span>Uptime: <strong id="uptime-display">--</strong></span>
									<span class="text-gray-300">|</span>
									<span>Load: <strong id="load-display">--</strong></span>
								</div>
							</div>
						</div>

						<!-- Grid de métricas -->
						<div class="metrics-grid">
							<!-- CPU -->
							<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
								<div class="flex items-center justify-between mb-4">
									<h3 class="text-lg font-semibold text-gray-800">
										<i class="fas fa-microchip text-blue-600 mr-2"></i>CPU
									</h3>
									<span id="cpu-cores" class="text-xs text-gray-500">-- cores</span>
								</div>

								<div class="relative pt-1">
									<div class="flex mb-2 items-center justify-between">
										<div>
											<span id="cpu-usage" class="text-3xl font-bold text-gray-700">--</span>
											<span class="text-sm text-gray-500 ml-1">%</span>
										</div>
										<div class="text-xs text-gray-500">
											<span>User: <strong id="cpu-user">--</strong>%</span>
											<span class="ml-2">Sys: <strong id="cpu-system">--</strong>%</span>
										</div>
									</div>
									<div class="overflow-hidden h-3 mb-4 text-xs flex rounded bg-gray-200">
										<div id="cpu-bar-user" style="width:0%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500 transition-all duration-500"></div>
										<div id="cpu-bar-system" style="width:0%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-red-400 transition-all duration-500"></div>
										<div id="cpu-bar-iowait" style="width:0%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-yellow-400 transition-all duration-500"></div>
									</div>
									<div class="flex text-xs text-gray-500 justify-between">
										<span><span class="inline-block w-2 h-2 bg-blue-500 rounded-full mr-1"></span>User</span>
										<span><span class="inline-block w-2 h-2 bg-red-400 rounded-full mr-1"></span>System</span>
										<span><span class="inline-block w-2 h-2 bg-yellow-400 rounded-full mr-1"></span>IOWait</span>
									</div>
								</div>
							</div>

							<!-- Memória -->
							<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
								<div class="flex items-center justify-between mb-4">
									<h3 class="text-lg font-semibold text-gray-800">
										<i class="fas fa-memory text-green-600 mr-2"></i>Memória RAM
									</h3>
									<span id="mem-total" class="text-xs text-gray-500">-- GB</span>
								</div>

								<div class="relative pt-1">
									<div class="flex mb-2 items-center justify-between">
										<div>
											<span id="mem-usage" class="text-3xl font-bold text-gray-700">--</span>
											<span class="text-sm text-gray-500 ml-1">%</span>
										</div>
										<div class="text-xs text-gray-500">
											<span>Usado: <strong id="mem-used">--</strong> MB</span>
										</div>
									</div>
									<div class="overflow-hidden h-3 mb-4 text-xs flex rounded bg-gray-200">
										<div id="mem-bar" style="width:0%" class="progress-bar shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-gradient-to-r from-green-400 to-green-600"></div>
									</div>
									<div class="grid grid-cols-3 gap-2 text-xs text-gray-500">
										<div>Livre: <strong id="mem-free">--</strong> MB</div>
										<div>Cache: <strong id="mem-cache">--</strong> MB</div>
										<div>Swap: <strong id="swap-usage">--</strong>%</div>
									</div>
								</div>
							</div>

							<!-- Disco -->
							<!--
							<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
								<div class="flex items-center justify-between mb-4">
									<h3 class="text-lg font-semibold text-gray-800">
										<i class="fas fa-hdd text-purple-600 mr-2"></i>Disco
									</h3>
									<span id="disk-total" class="text-xs text-gray-500">-- GB</span>
								</div>

								<div class="relative pt-1">
									<div class="flex mb-2 items-center justify-between">
										<div>
											<span id="disk-usage" class="text-3xl font-bold text-gray-700">--</span>
											<span class="text-sm text-gray-500 ml-1">%</span>
										</div>
										<div class="text-xs text-gray-500">
											<span>Livre: <strong id="disk-free">--</strong> GB</span>
										</div>
									</div>
									<div class="overflow-hidden h-3 mb-4 text-xs flex rounded bg-gray-200">
										<div id="disk-bar" style="width:0%" class="progress-bar shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-gradient-to-r from-purple-400 to-purple-600"></div>
									</div>
									<div class="text-xs text-gray-500">
										<span>Usado: <strong id="disk-used">--</strong> GB</span>
									</div>
								</div>
							</div>
							-->

							<!-- Rede -->
							<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
								<div class="flex items-center justify-between mb-4">
									<h3 class="text-lg font-semibold text-gray-800">
										<i class="fas fa-network-wired text-orange-600 mr-2"></i>Rede
									</h3>
									<span id="net-interface" class="text-xs text-gray-500">--</span>
								</div>

								<div class="space-y-4">
									<div>
										<div class="flex justify-between text-sm mb-1">
											<span class="text-gray-600"><i class="fas fa-download text-blue-500 mr-1"></i>Download</span>
											<span id="net-rx" class="font-semibold">--</span>
										</div>
										<div class="overflow-hidden h-2 text-xs flex rounded bg-gray-200">
											<div id="net-rx-bar" style="width:0%" class="progress-bar shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-400"></div>
										</div>
									</div>

									<div>
										<div class="flex justify-between text-sm mb-1">
											<span class="text-gray-600"><i class="fas fa-upload text-green-500 mr-1"></i>Upload</span>
											<span id="net-tx" class="font-semibold">--</span>
										</div>
										<div class="overflow-hidden h-2 text-xs flex rounded bg-gray-200">
											<div id="net-tx-bar" style="width:0%" class="progress-bar shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-green-400"></div>
										</div>
									</div>

									<div class="text-xs text-gray-500 pt-2 border-t border-gray-100">
										<span>Velocidade: <strong id="net-speed">--</strong> Mbps</span>
									</div>
								</div>
							</div>
						</div>
					</div>

            <!-- Estatísticas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                            <i class="fas fa-globe text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500 font-medium">Domínios Ativos</p>
                            <p class="text-2xl font-bold text-gray-800" id="total-domains">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500 font-medium">Total de Rotas</p>
                            <p class="text-2xl font-bold text-gray-800" id="total-routes">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 rounded-lg p-3">
                            <i class="fas fa-shield-alt text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500 font-medium">Rotas Públicas</p>
                            <p class="text-2xl font-bold text-gray-800" id="total-public">0</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barra de ações -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Domínios Configurados</h2>
                        <p class="text-sm text-gray-500 mt-1">Gerencie os domínios e suas respectivas configurações</p>
                    </div>
                    <button 
                        onclick="showCreateModal()"
                        class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-3 rounded-lg font-medium hover:from-blue-700 hover:to-blue-800 transition duration-200 transform hover:scale-[1.02] flex items-center">
                        <i class="fas fa-plus mr-2"></i>
                        Novo Domínio
                    </button>
                </div>
            </div>

            <!-- Tabela de domínios -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domínio</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Backend</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate Limit</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="domains-table-body" class="bg-white divide-y divide-gray-200">
                            <!-- Preenchido via JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Estado vazio -->
                <div id="empty-state" class="text-center py-12 hidden">
                    <i class="fas fa-server text-gray-300 text-6xl mb-4"></i>
                    <p class="text-gray-500 text-lg mb-2">Nenhum domínio configurado</p>
                    <p class="text-gray-400 text-sm">Clique em "Novo Domínio" para começar</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Criação/Edição -->
    <div id="domain-modal" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay absolute inset-0" onclick="closeModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto animate-fadeIn">
                <!-- Header do modal -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-t-2xl px-6 py-4 flex justify-between items-center">
                    <h3 id="modal-title" class="text-xl font-bold text-white">
                        <i class="fas fa-plus-circle mr-2"></i>Novo Domínio
                    </h3>
                    <button onclick="closeModal()" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Formulário -->
                <form id="domain-form" onsubmit="saveDomain(event)" class="p-6 space-y-6">
                    <input type="hidden" id="is-editing" value="false">
                    <input type="hidden" id="edit-domain-name" value="">
                    
                    <!-- Domínio -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-globe mr-1 text-blue-500"></i>Domínio
                        </label>
                        <input 
                            type="text" 
                            id="domain-input" 
                            required
                            placeholder="ex: api.meuservico.com"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>
                    
										<!-- Backend Host(s) -->
										<div>
											<label class="block text-sm font-semibold text-gray-700 mb-2">
												<i class="fas fa-server mr-1 text-green-500"></i>Backend Host(s)
											</label>
											<input
													type="text"
													id="backend-host"
													required
													placeholder="Ex: localhost:8080 ou 192.168.1.10:8080,https://192.168.1.11:8081"
													class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
											<p id="backend-hint" class="text-xs text-gray-500 mt-1">
											<i class="fas fa-info-circle mr-1"></i>
											Formato: <strong>host:porta</strong>. Para múltiplos backends, separe por vírgula.
											</p>
											<div id="backend-preview" class="mt-2 hidden">
												<div class="bg-gray-50 rounded-lg p-3">
													<p class="text-xs font-medium text-gray-600 mb-2">Backends detectados:</p>
													<div id="backend-list" class="space-y-1"></div>
												</div>
											</div>
										</div>

                    <!-- JWT Secret -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-key mr-1 text-yellow-500"></i>JWT Secret
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                id="jwt-secret" 
                                required
                                placeholder="Chave secreta para validação JWT"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <button 
                                type="button"
                                onclick="toggleJWTVisibility()"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                <i id="jwt-toggle-icon" class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Rate Limit -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tachometer-alt mr-1 text-orange-500"></i>Max Requisições
                            </label>
                            <input 
                                type="number" 
                                id="rate-limit-max" 
                                required
                                value="60"
                                placeholder="60"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-clock mr-1 text-orange-500"></i>Janela (segundos)
                            </label>
                            <input 
                                type="number" 
                                id="rate-limit-window" 
                                required
                                value="60"
                                placeholder="60"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        </div>
                    </div>
                    
                    <!-- Redis Config -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-database mr-1 text-red-500"></i>Configuração Redis
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Host</label>
                                <input 
                                    type="text" 
                                    id="redis-host" 
                                    required
                                    value="127.0.0.1"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Porta</label>
                                <input 
                                    type="number" 
                                    id="redis-port" 
                                    required
                                    value="6379"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Senha</label>
                                <input 
                                    type="text" 
                                    id="redis-password"
                                    placeholder="Opcional"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Database</label>
                                <input 
                                    type="number" 
                                    id="redis-db" 
                                    required
                                    value="0"
                                    min="0"
                                    max="15"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            </div>
                        </div>
                    </div>
                    
										<!-- Configuração de Load Balancing -->
										<div class="bg-gray-50 rounded-lg p-4">
											<h4 class="text-sm font-semibold text-gray-700 mb-3">
												<i class="fas fa-balance-scale mr-1 text-indigo-500"></i>Load Balancing
											</h4>

											<div class="flex items-center mb-3">
												<input
														type="checkbox"
														id="lb-enabled"
														class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
														onchange="toggleLoadBalancing()">
												<label for="lb-enabled" class="ml-2 text-sm text-gray-700">Habilitar Balanceamento de Carga</label>
											</div>

											<div id="lb-config" class="hidden space-y-3">
												<div>
													<label class="block text-xs text-gray-600 mb-1">Algoritmo</label>
													<select id="lb-algorithm" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
														<option value="round-robin">Round Robin</option>
														<option value="least-connections">Least Connections (em breve)</option>
													</select>
												</div>

												<div class="flex items-center">
													<input
															type="checkbox"
															id="lb-health-check"
															checked
															class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
													<label for="lb-health-check" class="ml-2 text-sm text-gray-700">Health Check automático</label>
												</div>

												<div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
													<p class="text-xs text-blue-700">
													<i class="fas fa-info-circle mr-1"></i>
													Para adicionar múltiplos backends, separe os hosts por vírgula no campo "Backend Host".
													Ex: <code class="bg-blue-100 px-1 rounded">192.168.1.10:8080,192.168.1.11:8080</code>
													</p>
												</div>
											</div>
										</div>

                    <!-- Rotas Públicas -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-unlock mr-1 text-purple-500"></i>Rotas Públicas (sem JWT)
                        </label>
                        <textarea 
                            id="public-paths" 
                            rows="3"
                            placeholder='Ex: #^/public/#, #^/health$#'
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"></textarea>
                        <p class="text-xs text-gray-500 mt-1">Separe múltiplas rotas por vírgula ou linha</p>
                    </div>
                    
                    <!-- Botões de ação -->
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                        <button 
                            type="button"
                            onclick="closeModal()"
                            class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition">
                            Cancelar
                        </button>
                        <button 
                            type="submit"
                            id="save-button"
                            class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg font-medium hover:from-blue-700 hover:to-blue-800 transition transform hover:scale-[1.02]">
                            <i class="fas fa-save mr-2"></i>Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div id="delete-modal" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay absolute inset-0" onclick="closeDeleteModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-fadeIn">
                <div class="text-center p-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Confirmar Exclusão</h3>
                    <p class="text-gray-500 mb-2">
                        Tem certeza que deseja excluir o domínio:
                    </p>
                    <p id="delete-domain-name" class="text-lg font-semibold text-gray-700 bg-gray-50 rounded-lg py-2 px-4 mb-6"></p>
                    <div class="flex justify-center space-x-3">
                        <button 
                            onclick="closeDeleteModal()"
                            class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition">
                            Cancelar
                        </button>
                        <button 
                            id="confirm-delete-btn"
                            class="px-6 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg font-medium hover:from-red-700 hover:to-red-800 transition">
                            <i class="fas fa-trash mr-2"></i>Excluir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let adminToken = '';
        let allConfigs = {};

        // Inicialização
        document.addEventListener('DOMContentLoaded', () => {
            const saved = sessionStorage.getItem('adminToken');
            if (saved) {
                adminToken = saved;
                showDashboard();
                loadDomains();
            }
        });

        // Toggle visibilidade do token
        function toggleTokenVisibility() {
            const input = document.getElementById('token-input');
            const icon = document.getElementById('toggle-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            }
        }

        function toggleJWTVisibility() {
            const input = document.getElementById('jwt-secret');
            const icon = document.getElementById('jwt-toggle-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            }
        }

        // Login
        async function login(event) {
            event.preventDefault();
            const token = document.getElementById('token-input').value.trim();
            if (!token) {
                showToast('Token é obrigatório', 'error');
                return;
            }

            try {
                const resp = await fetch('/admin/api/domains', { 
                    headers: { 'X-Admin-Token': token } 
                });
                
                if (!resp.ok) throw new Error('Token inválido');
                
                adminToken = token;
                sessionStorage.setItem('adminToken', token);
                showDashboard();
                await loadDomains();
                showToast('Autenticado com sucesso!', 'success');
            } catch (error) {
                showToast('Token inválido. Verifique e tente novamente.', 'error');
            }
        }

        // Logout
        function logout() {
            adminToken = '';
            sessionStorage.removeItem('adminToken');
            document.getElementById('login-screen').classList.remove('hidden');
            document.getElementById('dashboard').classList.add('hidden');
            document.getElementById('user-actions').innerHTML = '';
            showToast('Sessão encerrada', 'info');
        }

        // Mostrar dashboard
        function showDashboard() {
            document.getElementById('login-screen').classList.add('hidden');
            document.getElementById('dashboard').classList.remove('hidden');
            
            // Botão de logout no header
            document.getElementById('user-actions').innerHTML = `
                <button onclick="logout()" 
                    class="flex items-center space-x-2 bg-white/20 text-white px-4 py-2 rounded-lg hover:bg-white/30 transition">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sair</span>
                </button>
            `;
        }

        // Carregar domínios
        async function loadDomains() {
            try {
                const resp = await fetch('/admin/api/domains', { 
                    headers: { 'X-Admin-Token': adminToken } 
                });
                allConfigs = await resp.json();
                renderDomainsTable();
                updateStats();
            } catch (error) {
                showToast('Erro ao carregar domínios', 'error');
            }
        }

        // Renderizar tabela
        function renderDomainsTable() {
            const tbody = document.getElementById('domains-table-body');
            const emptyState = document.getElementById('empty-state');
            const domains = Object.keys(allConfigs);

            if (domains.length === 0) {
                tbody.innerHTML = '';
                emptyState.classList.remove('hidden');
                return;
            }

            emptyState.classList.add('hidden');
            tbody.innerHTML = domains.map(domain => {
                const config = allConfigs[domain];
                const totalRoutes = config.public_paths ? config.public_paths.length : 0;
                
                return `
                    <tr class="hover:bg-gray-50 transition animate-slideIn">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-globe text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">${domain}</div>
                                    <div class="text-xs text-gray-500">${totalRoutes} rota(s) pública(s)</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <i class="fas fa-server mr-1"></i>
                                    ${config.backend_host}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700">
                                ${config.rate_limit_max_requests}/<span class="text-xs text-gray-500">${config.rate_limit_window_seconds}s</span>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                <i class="fas fa-check-circle mr-1"></i>Ativo
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end space-x-2">
                                <button 
                                    onclick="editDomain('${domain}')"
                                    class="text-blue-600 hover:text-blue-900 p-2 hover:bg-blue-50 rounded-lg transition"
                                    title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button 
                                    onclick="confirmDelete('${domain}')"
                                    class="text-red-600 hover:text-red-900 p-2 hover:bg-red-50 rounded-lg transition"
                                    title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Atualizar estatísticas
        function updateStats() {
            const domains = Object.keys(allConfigs);
            document.getElementById('total-domains').textContent = domains.length;
            
            let totalRoutes = 0;
            let totalPublic = 0;
            domains.forEach(domain => {
                totalRoutes += 1; // cada domínio tem pelo menos a rota principal
                totalPublic += allConfigs[domain].public_paths ? allConfigs[domain].public_paths.length : 0;
            });
            
            document.getElementById('total-routes').textContent = totalRoutes;
            document.getElementById('total-public').textContent = totalPublic;
        }

        // Mostrar modal de criação
        function showCreateModal() {
            document.getElementById('modal-title').innerHTML = '<i class="fas fa-plus-circle mr-2"></i>Novo Domínio';
            document.getElementById('is-editing').value = 'false';
            document.getElementById('edit-domain-name').value = '';
            document.getElementById('domain-input').readOnly = false;
            
            // Limpar formulário
            document.getElementById('domain-form').reset();
            document.getElementById('domain-input').value = '';
            document.getElementById('redis-host').value = '127.0.0.1';
            document.getElementById('redis-port').value = '6379';
            document.getElementById('redis-db').value = '0';
            document.getElementById('rate-limit-max').value = '60';
            document.getElementById('rate-limit-window').value = '60';
            
            document.getElementById('domain-modal').classList.remove('hidden');
        }

        // Editar domínio
        function editDomain(domain) {
            const config = allConfigs[domain];
            
            document.getElementById('modal-title').innerHTML = '<i class="fas fa-edit mr-2"></i>Editar Domínio';
            document.getElementById('is-editing').value = 'true';
            document.getElementById('edit-domain-name').value = domain;
            
            document.getElementById('domain-input').value = domain;
            document.getElementById('domain-input').readOnly = true;
            document.getElementById('backend-host').value = config.backend_host;
            document.getElementById('jwt-secret').value = config.jwt_secret;
            document.getElementById('rate-limit-max').value = config.rate_limit_max_requests;
            document.getElementById('rate-limit-window').value = config.rate_limit_window_seconds;
            document.getElementById('redis-host').value = config.redis.host;
            document.getElementById('redis-port').value = config.redis.port;
            document.getElementById('redis-password').value = config.redis.password || '';
            document.getElementById('redis-db').value = config.redis.database;
            document.getElementById('public-paths').value = config.public_paths.join(', ');
            
            document.getElementById('domain-modal').classList.remove('hidden');
        }

        // Fechar modal
        function closeModal() {
            document.getElementById('domain-modal').classList.add('hidden');
        }

        // Salvar domínio
        async function saveDomain(event) {
            event.preventDefault();
            
            const isEditing = document.getElementById('is-editing').value === 'true';
            const domain = document.getElementById('domain-input').value.trim().toLowerCase();
            
            if (!domain) {
                showToast('Domínio é obrigatório', 'error');
                return;
            }

            const payload = {
                backend_host: document.getElementById('backend-host').value.trim(),
                jwt_secret: document.getElementById('jwt-secret').value.trim(),
                rate_limit_max_requests: parseInt(document.getElementById('rate-limit-max').value),
                rate_limit_window_seconds: parseInt(document.getElementById('rate-limit-window').value),
                redis: {
                    host: document.getElementById('redis-host').value.trim(),
                    port: parseInt(document.getElementById('redis-port').value),
                    password: document.getElementById('redis-password').value.trim() || null,
                    database: parseInt(document.getElementById('redis-db').value),
                    timeout: 2.5
                },
                public_paths: document.getElementById('public-paths').value
                    .split(/[,\n]/)
                    .map(s => s.trim())
                    .filter(s => s)
            };

            try {
                const url = isEditing 
                    ? `/admin/api/domains/${domain}`
                    : '/admin/api/domains';
                
                const method = isEditing ? 'PUT' : 'POST';
                
                if (!isEditing) {
                    payload.domain = domain;
                }

                const resp = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Admin-Token': adminToken
                    },
                    body: JSON.stringify(payload)
                });

                if (!resp.ok) {
                    const error = await resp.json();
                    throw new Error(error.error || 'Erro ao salvar');
                }

                closeModal();
                await loadDomains();
                showToast(
                    isEditing ? 'Domínio atualizado com sucesso!' : 'Domínio criado com sucesso!',
                    'success'
                );
            } catch (error) {
                showToast(error.message, 'error');
            }
        }

        // Confirmar exclusão
        function confirmDelete(domain) {
            document.getElementById('delete-domain-name').textContent = domain;
            document.getElementById('confirm-delete-btn').onclick = () => deleteDomain(domain);
            document.getElementById('delete-modal').classList.remove('hidden');
        }

        // Fechar modal de exclusão
        function closeDeleteModal() {
            document.getElementById('delete-modal').classList.add('hidden');
        }

        // Deletar domínio
        async function deleteDomain(domain) {
            try {
                const resp = await fetch(`/admin/api/domains/${domain}`, {
                    method: 'DELETE',
                    headers: { 'X-Admin-Token': adminToken }
                });

                if (!resp.ok) {
                    const error = await resp.json();
                    throw new Error(error.error || 'Erro ao excluir');
                }

                closeDeleteModal();
                await loadDomains();
                showToast('Domínio excluído com sucesso!', 'success');
            } catch (error) {
                showToast(error.message, 'error');
            }
        }

        // Toast de notificação
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500',
                warning: 'bg-yellow-500'
            };
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                info: 'fa-info-circle',
                warning: 'fa-exclamation-triangle'
            };

            const toast = document.createElement('div');
            toast.className = `toast ${colors[type]} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3`;
            toast.innerHTML = `
                <i class="fas ${icons[type]} text-xl"></i>
                <span class="font-medium">${message}</span>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s ease-out';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Fechar modais com ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
                closeDeleteModal();
            }
        });

				// ============================================
				// Monitoramento do Sistema em Tempo Real
				// ============================================

					let previousNetworkStats = null;
				let previousTimestamp = null;

				// Função para atualizar as métricas
				async function updateSystemStats() {
					try {
						const resp = await fetch('/admin/api/system-stats', {
							headers: { 'X-Admin-Token': adminToken }
						});

						if (!resp.ok) return;

						const stats = await resp.json();
						updateCPUStats(stats.cpu);
						updateMemoryStats(stats.memory);
						// updateDiskStats(stats.disk);
						updateNetworkStats(stats.network);
						updateGeneralInfo(stats);

						// Atualizar indicador de última atualização
						const now = new Date();
						document.getElementById('last-update').textContent =
							`Atualizado: ${now.toLocaleTimeString()}`;

						// Piscar indicador
						const indicator = document.getElementById('update-indicator');
						indicator.classList.add('pulse');
						setTimeout(() => indicator.classList.remove('pulse'), 2000);

					} catch (error) {
						console.error('Erro ao atualizar estatísticas:', error);
					}
				}

				// Atualizar estatísticas da CPU
				function updateCPUStats(cpu) {
					document.getElementById('cpu-usage').textContent = cpu.usage;
					document.getElementById('cpu-cores').textContent = `${cpu.cores} cores`;
					document.getElementById('cpu-user').textContent = cpu.details.user;
					document.getElementById('cpu-system').textContent = cpu.details.system;

					// Barras de progresso
					document.getElementById('cpu-bar-user').style.width = cpu.details.user + '%';
					document.getElementById('cpu-bar-system').style.width = cpu.details.system + '%';
					document.getElementById('cpu-bar-iowait').style.width = cpu.details.iowait + '%';

					// Cor baseada no uso
					const cpuUsageEl = document.getElementById('cpu-usage');
					if (cpu.usage > 90) {
						cpuUsageEl.className = 'text-3xl font-bold text-red-600';
					} else if (cpu.usage > 70) {
						cpuUsageEl.className = 'text-3xl font-bold text-yellow-600';
					} else {
						cpuUsageEl.className = 'text-3xl font-bold text-gray-700';
					}
				}

				// Atualizar estatísticas de memória
				function updateMemoryStats(mem) {
					document.getElementById('mem-usage').textContent = mem.usage_percent;
					document.getElementById('mem-total').textContent = `${mem.total} MB`;
					document.getElementById('mem-used').textContent = mem.used;
					document.getElementById('mem-free').textContent = mem.free;
					document.getElementById('mem-cache').textContent = (mem.buffers + mem.cached).toFixed(1);
					document.getElementById('swap-usage').textContent = `${mem.swap_usage_percent}%`;

					// Barra de progresso
					document.getElementById('mem-bar').style.width = mem.usage_percent + '%';

					// Cor baseada no uso
					const memBar = document.getElementById('mem-bar');
					if (mem.usage_percent > 90) {
						memBar.className = 'progress-bar shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-gradient-to-r from-red-400 to-red-600';
					} else if (mem.usage_percent > 70) {
						memBar.className = 'progress-bar shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-gradient-to-r from-yellow-400 to-yellow-600';
					} else {
						memBar.className = 'progress-bar shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-gradient-to-r from-green-400 to-green-600';
					}
				}

				// Atualizar estatísticas de disco
				function updateDiskStats(disk) {
					// document.getElementById('disk-usage').textContent = disk.usage_percent;
					document.getElementById('disk-total').textContent = `${disk.total} GB`;
					document.getElementById('disk-used').textContent = disk.used;
					document.getElementById('disk-free').textContent = disk.free;

					// Barra de progresso
					document.getElementById('disk-bar').style.width = disk.usage_percent + '%';

					// Cor baseada no uso
					const diskBar = document.getElementById('disk-bar');
					if (disk.usage_percent > 90) {
						diskBar.className = 'progress-bar shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-gradient-to-r from-red-400 to-red-600';
					} else if (disk.usage_percent > 70) {
						diskBar.className = 'progress-bar shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-gradient-to-r from-yellow-400 to-yellow-600';
					} else {
						diskBar.className = 'progress-bar shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-gradient-to-r from-purple-400 to-purple-600';
					}
				}

				// Atualizar estatísticas de rede
				function updateNetworkStats(network) {
					if (Object.keys(network).length === 0) return;

					const iface = Object.keys(network)[0];
					const stats = network[iface];

					document.getElementById('net-interface').textContent = iface;
					document.getElementById('net-rx').textContent = `${stats.rx_mb} MB`;
					document.getElementById('net-tx').textContent = `${stats.tx_mb} MB`;
					document.getElementById('net-speed').textContent = stats.speed_mbps;

					// Calcular velocidade de transferência (se tiver dados anteriores)
					if (previousNetworkStats && previousTimestamp) {
						const timeDiff = (Date.now() - previousTimestamp) / 1000; // segundos

						if (timeDiff > 0 && previousNetworkStats[iface]) {
							const rxDiff = (stats.rx_bytes - previousNetworkStats[iface].rx_bytes) / timeDiff;
							const txDiff = (stats.tx_bytes - previousNetworkStats[iface].tx_bytes) / timeDiff;

							const rxMbps = (rxDiff * 8 / 1000000).toFixed(2);
							const txMbps = (txDiff * 8 / 1000000).toFixed(2);

							document.getElementById('net-rx').textContent = `${rxMbps} Mbps`;
							document.getElementById('net-tx').textContent = `${txMbps} Mbps`;

							// Barras de progresso baseadas na velocidade máxima
							const maxSpeed = stats.speed_mbps > 0 ? stats.speed_mbps : 1000;
							document.getElementById('net-rx-bar').style.width = Math.min((rxMbps / maxSpeed) * 100, 100) + '%';
							document.getElementById('net-tx-bar').style.width = Math.min((txMbps / maxSpeed) * 100, 100) + '%';
						}
					}

					// Armazenar para próximo cálculo
					previousNetworkStats = network;
					previousTimestamp = Date.now();
				}

				// Atualizar informações gerais
				function updateGeneralInfo(stats) {
					document.getElementById('uptime-display').textContent = stats.uptime.formatted;
					document.getElementById('load-display').textContent =
						`${stats.load[0].toFixed(2)} / ${stats.load[1].toFixed(2)} / ${stats.load[2].toFixed(2)}`;
				}

				// Iniciar atualização periódica quando o dashboard estiver visível
				function startSystemMonitoring() {
					updateSystemStats(); // Primeira atualização imediata
					setInterval(updateSystemStats, 2000); // Atualizar a cada 1 segundo
				}

				// Modificar a função showDashboard() para iniciar o monitoramento
				const originalShowDashboard = showDashboard;
				showDashboard = function() {
					originalShowDashboard();
					startSystemMonitoring();
				};

				// Toggle configurações de load balancing
				function toggleLoadBalancing() {
					const enabled = document.getElementById('lb-enabled').checked;
					const config = document.getElementById('lb-config');

					if (enabled) {
						config.classList.remove('hidden');
					} else {
						config.classList.add('hidden');
					}
				}

				// Preview dos backends configurados
				function updateBackendPreview() {
						const hostValue = document.getElementById('backend-host').value.trim();
						const preview = document.getElementById('backend-preview');
						const list = document.getElementById('backend-list');
						
						if (!hostValue) {
								preview.classList.add('hidden');
								return;
						}
						
						const hosts = hostValue.split(',').filter(h => h.trim());
						
						if (hosts.length === 0) {
								preview.classList.add('hidden');
								return;
						}
						
						preview.classList.remove('hidden');
						
						list.innerHTML = hosts.map((host, index) => {
								const trimmed = host.trim();
								let scheme = 'http';
								let hostname = trimmed;
								let port = '80';
								
								// Detectar protocolo
								const protocolMatch = trimmed.match(/^(https?):\/\/(.+)$/i);
								if (protocolMatch) {
										scheme = protocolMatch[1].toLowerCase();
										hostname = protocolMatch[2];
										port = scheme === 'https' ? '443' : '80';
								}
								
								// Detectar porta
								const portMatch = hostname.match(/:(\d+)$/);
								if (portMatch) {
										port = portMatch[1];
										hostname = hostname.replace(/:\d+$/, '');
								} else if (port === '80' && scheme === 'https') {
										port = '443';
								}
								
								const schemeColors = {
										'http': 'bg-blue-50 border-blue-200',
										'https': 'bg-green-50 border-green-200'
								};
								
								const schemeIcons = {
										'http': 'fa-globe text-blue-500',
										'https': 'fa-lock text-green-500'
								};
								
								return `
										<div class="flex items-center justify-between ${schemeColors[scheme]} border rounded-lg px-3 py-2.5">
												<div class="flex items-center space-x-3">
														<span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-white text-xs font-bold text-gray-600 shadow-sm">
																${index + 1}
														</span>
														<div>
																<div class="flex items-center space-x-2">
																		<i class="fas ${schemeIcons[scheme]} text-xs"></i>
																		<span class="text-sm font-medium text-gray-700">${hostname}</span>
																</div>
																<span class="text-xs text-gray-500">${scheme.toUpperCase()} | Porta ${port}</span>
														</div>
												</div>
												<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-white text-gray-600 border border-gray-200">
														<i class="fas fa-plug mr-1 text-xs"></i>${port}
												</span>
										</div>
								`;
						}).join('');
						
						// Atualizar hint baseado no número de backends
						const hint = document.getElementById('backend-hint');
						const hasHttps = hosts.some(h => h.trim().toLowerCase().startsWith('https://'));
						
						if (hosts.length > 1) {
								hint.innerHTML = `
										<i class="fas fa-balance-scale mr-1 text-indigo-500"></i>
										<strong>${hosts.length} backends</strong> detectados (${hasHttps ? 'incluindo HTTPS' : 'apenas HTTP'}). 
										${document.getElementById('lb-enabled')?.checked ? 'Load balancing ativo.' : 'Ative o load balancing abaixo.'}
								`;
						} else {
								hint.innerHTML = `
										<i class="fas fa-info-circle mr-1"></i>
										Formatos aceitos: <strong>host:porta</strong>, <strong>http://host:porta</strong> ou <strong>https://host:porta</strong>
								`;
						}
				}

				// Monitorar mudanças no campo backend_host
				document.getElementById('backend-host').addEventListener('input', updateBackendPreview);

				// Atualizar função showCreateModal
				const originalShowCreateModal = showCreateModal;
				showCreateModal = function() {
					originalShowCreateModal();

					// Resetar backend
					document.getElementById('backend-host').value = '';
					document.getElementById('backend-preview').classList.add('hidden');
					document.getElementById('lb-enabled').checked = false;
					toggleLoadBalancing();
				};

				// Atualizar função editDomain
				const originalEditDomain = editDomain;
				editDomain = function(domain) {
					// Chamar função original primeiro
					document.getElementById('modal-title').innerHTML = '<i class="fas fa-edit mr-2"></i>Editar Domínio';
					document.getElementById('is-editing').value = 'true';
					document.getElementById('edit-domain-name').value = domain;

					const config = allConfigs[domain];

					// Preencher campos
					document.getElementById('domain-input').value = domain;
					document.getElementById('domain-input').readOnly = true;
					document.getElementById('backend-host').value = config.backend_host;
					document.getElementById('jwt-secret').value = config.jwt_secret;
					document.getElementById('rate-limit-max').value = config.rate_limit_max_requests;
					document.getElementById('rate-limit-window').value = config.rate_limit_window_seconds;
					document.getElementById('redis-host').value = config.redis.host;
					document.getElementById('redis-port').value = config.redis.port;
					document.getElementById('redis-password').value = config.redis.password || '';
					document.getElementById('redis-db').value = config.redis.database;
					document.getElementById('public-paths').value = config.public_paths.join(', ');

					// Load balancing
					const lb = config.load_balancing || { enabled: false };
					document.getElementById('lb-enabled').checked = lb.enabled || false;
					document.getElementById('lb-algorithm').value = lb.algorithm || 'round-robin';
					document.getElementById('lb-health-check').checked = lb.health_check !== false;

					toggleLoadBalancing();
					updateBackendPreview();
					document.getElementById('domain-modal').classList.remove('hidden');
				};

				// Atualizar função saveDomain
				const originalSaveDomain = saveDomain;
				saveDomain = async function(event) {
					event.preventDefault();

					const isEditing = document.getElementById('is-editing').value === 'true';
					const domain = document.getElementById('domain-input').value.trim().toLowerCase();
					const hostValue = document.getElementById('backend-host').value.trim();

					if (!domain) {
						showToast('Domínio é obrigatório', 'error');
						return;
					}

					if (!hostValue) {
						showToast('Backend Host é obrigatório', 'error');
						return;
					}

					// Validar formato
					const hosts = hostValue.split(',').filter(h => h.trim());
					const invalidHosts = hosts.filter(h => !h.includes(':'));

					if (invalidHosts.length > 0) {
						showToast(`Hosts sem porta especificada: ${invalidHosts.join(', ')}. Use o formato host:porta`, 'error');
						return;
					}

					const payload = {
						backend_host: hostValue,
						jwt_secret: document.getElementById('jwt-secret').value.trim(),
						rate_limit_max_requests: parseInt(document.getElementById('rate-limit-max').value),
						rate_limit_window_seconds: parseInt(document.getElementById('rate-limit-window').value),
						redis: {
							host: document.getElementById('redis-host').value.trim(),
							port: parseInt(document.getElementById('redis-port').value),
							password: document.getElementById('redis-password').value.trim() || null,
							database: parseInt(document.getElementById('redis-db').value),
							timeout: 2.5
						},
						public_paths: document.getElementById('public-paths').value
						.split(/[,\n]/)
						.map(s => s.trim())
						.filter(s => s),
						load_balancing: {
							enabled: document.getElementById('lb-enabled').checked,
							algorithm: document.getElementById('lb-algorithm').value,
							health_check: document.getElementById('lb-health-check').checked
						}
					};

					try {
						const url = isEditing
							? `/admin/api/domains/${domain}`
							: '/admin/api/domains';

						const method = isEditing ? 'PUT' : 'POST';

						if (!isEditing) {
							payload.domain = domain;
						}

						const resp = await fetch(url, {
							method,
							headers: {
								'Content-Type': 'application/json',
								'X-Admin-Token': adminToken
							},
							body: JSON.stringify(payload)
						});

						if (!resp.ok) {
							const error = await resp.json();
							throw new Error(error.error || 'Erro ao salvar');
						}

						closeModal();
						await loadDomains();
						showToast(
							isEditing ? 'Domínio atualizado com sucesso!' : 'Domínio criado com sucesso!',
							'success'
						);
					} catch (error) {
						showToast(error.message, 'error');
					}
				};
    </script>
</body>
</html>
