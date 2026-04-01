<x-app-layout>
    <div class="h-screen bg-gray-900 overflow-hidden flex flex-col" 
        x-data="{ 
            currentRoomId: null,
            currentRoomName: 'Lobby',
            isJoining: false,
            joiningRoomId: null,
            roomUsers: [],
            userPos: { x: 50, y: 50 },

            isMicEnabled: false,
            isCamEnabled: false,

            init() {
                window.addEventListener('beforeunload', () => this.leaveRoom());
                
                setInterval(() => {
                    if (this.currentRoomId && window.Echo) {
                        try {
                            window.Echo.join(`room.${this.currentRoomId}`)
                                .whisper('moving', {
                                    id: {{ Auth::id() }},
                                    x: this.userPos.x,
                                    y: this.userPos.y
                                });
                        } catch (e) {}
                    }
                }, 100);
            },

            handleKeydown(e) {
                if (!this.currentRoomId) return;
                
                const step = 4; // Increased speed for better feel
                let moved = false;

                if (['w', 'ArrowUp', 'W'].includes(e.key)) { this.userPos.y -= step; moved = true; }
                if (['s', 'ArrowDown', 'S'].includes(e.key)) { this.userPos.y += step; moved = true; }
                if (['a', 'ArrowLeft', 'A'].includes(e.key)) { this.userPos.x -= step; moved = true; }
                if (['d', 'ArrowRight', 'D'].includes(e.key)) { this.userPos.x += step; moved = true; }

                if (moved) {
                    this.userPos.x = Math.max(0, Math.min(100, this.userPos.x));
                    this.userPos.y = Math.max(0, Math.min(100, this.userPos.y));
                }
            },

            handleMapClick(e) {
                if (!this.currentRoomId) return;
                const rect = e.currentTarget.getBoundingClientRect();
                this.userPos.x = ((e.clientX - rect.left) / rect.width) * 100;
                this.userPos.y = ((e.clientY - rect.top) / rect.height) * 100;
                console.log(`[ClickMove] X: ${this.userPos.x.toFixed(1)}%, Y: ${this.userPos.y.toFixed(1)}%`);
            },

            listenForMovement(roomId) {
                window.Echo.join(`room.${roomId}`)
                    .listenForWhisper('moving', (e) => {
                        const user = this.roomUsers.find(u => u.id === e.id);
                        if (user) {
                            user.x = e.x;
                            user.y = e.y;
                        }
                    });
            },

            async joinRoom(roomId, roomName) {
                if (this.currentRoomId === roomId) return;
                
                await window.lk_leaveRoom();
                
                if (this.currentRoomId) {
                    window.Echo.leave(`room.${this.currentRoomId}`);
                }

                this.isJoining = true;
                this.joiningRoomId = roomId;

                try {
                    const response = await fetch(`/rooms/${roomId}/join`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (response.ok) {
                        this.currentRoomId = roomId;
                        this.currentRoomName = roomName;
                        this.roomUsers = [];
                        
                        window.Echo.join(`room.${roomId}`)
                            .here((users) => { this.roomUsers = users; })
                            .joining((user) => { this.roomUsers.push(user); })
                            .leaving((user) => { this.roomUsers = this.roomUsers.filter(u => u.id !== user.id); });

                        this.listenForMovement(roomId);
                        window.lk_joinRoom(data.livekit_url, data.token);
                    }
                } catch (error) {
                    console.error('Failed to join room', error);
                } finally {
                    this.isJoining = false;
                    this.joiningRoomId = null;
                }
            },

            async toggleMic() {
                this.isMicEnabled = await window.lk_toggleMic();
            },

            async toggleCam() {
                this.isCamEnabled = await window.lk_toggleCam();
            },

            async leaveRoom() {
                await window.lk_leaveRoom();
                if (this.currentRoomId) {
                    window.Echo.leave(`room.${this.currentRoomId}`);
                }
                try {
                    await fetch(`/rooms/leave`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    this.currentRoomId = null;
                    this.currentRoomName = 'Lobby';
                    this.roomUsers = [];
                } catch (error) {
                    console.error('Failed to leave room', error);
                }
            }
        }"
        @keydown.window="handleKeydown($event)"
    >
        <!-- Top Bar -->
        <header class="bg-gray-800 border-b border-gray-700 px-8 py-4 flex items-center justify-between relative z-20">
            <div class="flex items-center space-x-4">
                <a href="{{ route('spaces.index') }}" class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-xl font-bold text-white">{{ $space->name }}</h1>
            </div>

            <div class="flex items-center space-x-6">
                <div class="flex items-center space-x-2 bg-indigo-500/10 px-4 py-2 rounded-full border border-indigo-500/20">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="text-indigo-200 text-sm font-medium">In <span x-text="currentRoomId ? currentRoomName : 'Searching for a Room...'">Lobby</span></span>
                </div>
                
                <div class="w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center text-white font-bold ring-2 ring-indigo-500 shadow-xl overflow-hidden">
                    <img src="{{ Auth::user()->avatar ?? 'https://ui-avatars.com/api/?name='.Auth::user()->name.'&background=random' }}" alt="{{ Auth::user()->name }}">
                </div>
            </div>
        </header>

        <main class="flex-1 flex overflow-hidden">
            <!-- Sidebar -->
            <aside class="w-72 bg-gray-800 border-r border-gray-700 flex flex-col relative z-20">
                <div class="p-6">
                    <h2 class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-6 px-2">Rooms</h2>
                    <nav class="space-y-3">
                        @foreach($space->rooms as $room)
                            <button 
                                @click="joinRoom({{ $room->id }}, '{{ $room->name }}')"
                                :class="currentRoomId === {{ $room->id }} ? 'bg-indigo-600 text-white shadow-xl translate-x-2' : 'bg-gray-700/50 text-gray-300 hover:bg-gray-700 hover:text-white hover:translate-x-1'"
                                class="w-full flex items-center justify-between p-4 rounded-xl font-semibold transition-all duration-300 text-left relative overflow-hidden group">
                                <div class="flex items-center z-10 text-sm">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    {{ $room->name }}
                                </div>
                                <span class="text-[10px] bg-gray-800/50 px-2 py-1 rounded text-gray-400 z-10">{{ $room->presences->count() }}</span>
                            </button>
                        @endforeach
                    </nav>
                </div>
                
                <div class="mt-auto p-6 border-t border-gray-700 bg-gray-850">
                    <button @click="leaveRoom()" class="w-full bg-red-500/10 hover:bg-red-500/20 text-red-500 font-bold py-3 px-6 rounded-xl transition-all duration-300 flex items-center justify-center space-x-2">
                        <span>Leave Room</span>
                    </button>
                </div>
            </aside>

            <!-- Main Stage -->
            <section class="flex-1 bg-gray-900 border-l border-gray-700 flex flex-col relative overflow-hidden">
                <div class="relative z-10 h-full flex flex-col p-8">
                    <div x-show="!currentRoomId" class="flex-1 flex flex-col items-center justify-center text-center">
                        <h2 class="text-2xl font-extrabold text-white mb-4">Choose a room to begin</h2>
                    </div>

                    <div x-show="currentRoomId" class="h-full flex flex-col">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-3xl font-black text-white mb-1" x-text="currentRoomName"></h2>
                                <p class="text-indigo-400 font-semibold tracking-wide uppercase text-[10px]">Active Session • Use WASD to Move</p>
                            </div>

                            <div class="flex space-x-2">
                                <!-- Mic Toggle -->
                                <button 
                                    @click="toggleMic()"
                                    :class="isMicEnabled ? 'bg-indigo-600' : 'bg-red-600'"
                                    class="w-10 h-10 rounded-xl flex items-center justify-center text-white transition-all shadow-lg hover:scale-110">
                                    <svg x-show="isMicEnabled" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
                                    <svg x-show="!isMicEnabled" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                </button>
                                <!-- Camera Toggle -->
                                <button 
                                    @click="toggleCam()"
                                    :class="isCamEnabled ? 'bg-indigo-600' : 'bg-red-600'"
                                    class="w-10 h-10 rounded-xl flex items-center justify-center text-white transition-all shadow-lg hover:scale-110">
                                    <svg x-show="isCamEnabled" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                    <svg x-show="!isCamEnabled" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                </button>
                            </div>
                        </div>

                        <!-- 2D Map (Software HQ) -->
                        <div class="relative w-full h-[500px] bg-[#0c0e14] rounded-3xl border border-gray-700/50 mb-8 overflow-hidden shadow-[inset_0_4px_20px_rgba(0,0,0,0.5)] cursor-crosshair group/map"
                             @click="handleMapClick($event)">
                            
                            <!-- Walls (Room Depth) -->
                            <div class="absolute left-0 top-0 w-2 h-full bg-indigo-500/10 border-r border-indigo-500/20"></div>
                            <div class="absolute right-0 top-0 w-2 h-full bg-indigo-500/10 border-l border-indigo-500/20"></div>
                            <div class="absolute right-0 bottom-0 w-full h-2 bg-indigo-500/10 border-t border-indigo-500/20"></div>

                            <!-- Decorative Plant -->
                            <div class="absolute top-10 left-10">
                                <div class="w-8 h-8 bg-green-500/20 border-2 border-green-500/40 rounded-full flex items-center justify-center animate-pulse shadow-[0_0_15px_rgba(34,197,94,0.3)]">
                                    <div class="w-4 h-4 bg-green-500/50 rounded-full"></div>
                                </div>
                                <div class="mt-2 text-[8px] text-green-500/50 font-bold uppercase text-center tracking-tighter">Zen Plant</div>
                            </div>

                            <!-- Grid Background -->
                            <div class="absolute inset-0" style="background-image: radial-gradient(#1e293b 1px, transparent 1px); background-size: 20px 20px; opacity: 0.3;"></div>
                            <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent"></div>

                            <!-- Room Sections: Server Room -->
                            <div class="absolute top-0 right-0 w-32 h-40 bg-black/40 border-l border-b border-gray-700/50 p-3 flex flex-col space-y-2">
                                <div class="text-[8px] text-gray-500 font-bold uppercase tracking-widest mb-1">Server Rack</div>
                                <div class="flex-1 flex flex-col justify-around">
                                    <template x-for="i in 4">
                                        <div class="h-1 bg-gray-800 rounded-full flex space-x-1 px-1 items-center">
                                            <div class="w-1 h-1 bg-green-500 rounded-full animate-pulse"></div>
                                            <div class="w-full h-[1px] bg-gray-700"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Room Sections: Workstations -->
                            <div class="absolute left-[15%] top-[25%] group/desk">
                                <div class="relative w-40 h-16 bg-[#1a1d26] border border-white/5 rounded-xl flex items-center justify-around shadow-2xl">
                                    <!-- Computers -->
                                    <div class="w-10 h-8 bg-gray-700/30 rounded border border-gray-600 flex items-center justify-center relative">
                                        <div class="w-6 h-4 bg-indigo-500/20 border border-indigo-400/30 rounded-sm overflow-hidden flex items-center justify-center">
                                            <div class="w-full h-[1px] bg-indigo-400/50 animate-pulse"></div>
                                        </div>
                                    </div>
                                    <div class="w-10 h-8 bg-gray-700/30 rounded border border-gray-600 flex items-center justify-center relative">
                                        <div class="w-6 h-4 bg-indigo-500/20 border border-indigo-400/30 rounded-sm overflow-hidden"></div>
                                    </div>
                                </div>
                                <div class="mt-2 text-[8px] text-gray-600 font-bold uppercase text-center tracking-widest">Dev Bay 01</div>
                            </div>

                            <div class="absolute right-[20%] top-[45%] group/desk">
                                <div class="relative w-32 h-20 bg-[#1a1d26] border border-white/5 rounded-xl flex items-center justify-around shadow-2xl">
                                    <div class="w-12 h-10 bg-gray-700/30 rounded border border-gray-600 flex items-center justify-center">
                                        <div class="w-8 h-6 bg-indigo-500/20 border border-indigo-400/30 rounded flex items-center justify-center">
                                            <svg class="w-4 h-4 text-indigo-400/50" fill="currentColor" viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z"/></svg>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2 text-[8px] text-gray-600 font-bold uppercase text-center tracking-widest">QA Station</div>
                            </div>

                            <!-- Room Sections: Coffee Area -->
                            <div class="absolute bottom-6 left-10 flex space-x-4">
                                <div class="w-12 h-12 bg-gray-800 rounded-full border border-gray-700 flex items-center justify-center shadow-xl">
                                    <svg class="w-6 h-6 text-orange-400/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <div class="flex flex-col justify-center">
                                    <div class="text-[10px] text-white font-bold">Coffee Station</div>
                                    <div class="text-[8px] text-gray-500">Unlimited Caffeine</div>
                                </div>
                            </div>

                            <!-- Meeting Zone -->
                            <div class="absolute left-[45%] top-[15%] w-32 h-32 border-2 border-dashed border-gray-800 rounded-full flex items-center justify-center opacity-40">
                                <span class="text-[8px] text-gray-600 font-black uppercase tracking-[0.2em]">Huddle Hub</span>
                            </div>
                            
                            <!-- Local Avatar -->
                            <div class="absolute transition-all duration-700 ease-out pointer-events-none" 
                                 :style="{ left: userPos.x + '%', top: userPos.y + '%' }"
                                 style="transform: translate(-50%, -50%);">
                                <div class="relative avatar-walking flex flex-col items-center">
                                    <div class="mb-1 drop-shadow-[0_10px_10px_rgba(0,0,0,0.5)]">
                                        <img src="https://api.dicebear.com/7.x/adventurer/svg?seed={{ Auth::user()->name }}&backgroundColor=transparent" 
                                             class="w-16 h-16 object-contain">
                                    </div>
                                    <div class="bg-indigo-600 px-2 py-0.5 rounded-full text-[8px] font-bold text-white whitespace-nowrap shadow-xl border border-indigo-500/50">You</div>
                                </div>
                            </div>
                            
                            <!-- Others (2D Adventurer People) -->
                            <template x-for="user in roomUsers.filter(u => u.id !== {{ Auth::id() }})" :key="user.id">
                                <div class="absolute transition-all duration-700 ease-out pointer-events-none" 
                                     :style="{ left: (user.x || 50) + '%', top: (user.y || 50) + '%' }"
                                     style="transform: translate(-50%, -50%);">
                                    <div class="relative avatar-walking flex flex-col items-center">
                                        <div class="mb-1 drop-shadow-[0_10px_10px_rgba(0,0,0,0.5)]">
                                            <img :src="'https://api.dicebear.com/7.x/adventurer/svg?seed=' + user.name + '&backgroundColor=transparent'" 
                                                 class="w-16 h-16 object-contain">
                                        </div>
                                        <div class="bg-gray-800 px-2 py-0.5 rounded-full text-[8px] font-bold text-white whitespace-nowrap shadow-xl border border-gray-700" x-text="user.name"></div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- User Grid -->
                        <div class="flex-1 grid grid-cols-2 lg:grid-cols-4 gap-6">
                            <div class="aspect-video bg-gray-800 rounded-3xl relative overflow-hidden group shadow-2xl border border-gray-700 ring-2 ring-indigo-500/20">
                                <div id="local-video-container" class="absolute inset-0"></div>
                                <div class="absolute bottom-4 left-4 text-white text-[10px] bg-black/50 px-2 py-1 rounded">You</div>
                            </div>
                            <template x-for="user in roomUsers.filter(u => u.id !== {{ Auth::id() }})" :key="user.id">
                                <div class="aspect-video bg-gray-800 rounded-3xl relative overflow-hidden group border border-gray-700 shadow-xl">
                                    <div :id="'participant-' + user.id" class="absolute inset-0"></div>
                                    <div class="absolute bottom-4 left-4 text-white text-[10px] bg-black/50 px-2 py-1 rounded" x-text="user.name"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- LiveKit Private Scope Script -->
    <script>
        window.lkRoomInstance = null;

        window.lk_joinRoom = async (url, token) => {
            try {
                if (window.lkRoomInstance) {
                    await window.lkRoomInstance.disconnect();
                }

                const room = new window.LiveKit.Room();
                window.lkRoomInstance = room;

                room.on(window.LiveKit.RoomEvent.TrackSubscribed, (track, publication, participant) => {
                    const id = participant.identity;
                    const container = document.getElementById(`participant-${id}`);
                    if (container) {
                        const el = track.attach();
                        el.setAttribute('data-track-id', track.sid);
                        el.classList.add('w-full', 'h-full', 'object-cover');
                        container.appendChild(el);
                    }
                });

                room.on(window.LiveKit.RoomEvent.TrackUnsubscribed, (track) => {
                    track.detach();
                    const el = document.querySelector(`[data-track-id='${track.sid}']`);
                    if (el) el.remove();
                });

                await room.connect(url, token);
                // Camera and Microphone are NOT enabled by default for privacy
                
                const localContainer = document.getElementById('local-video-container');
                if (localContainer) {
                    localContainer.innerHTML = '';
                    room.localParticipant.videoTrackPublications.forEach(pub => {
                        if (pub.track) {
                            const el = pub.track.attach();
                            el.classList.add('w-full', 'h-full', 'object-cover');
                            localContainer.appendChild(el);
                        }
                    });
                }
            } catch (err) { console.error('LiveKit Error', err); }
        };

        window.lk_leaveRoom = async () => {
            if (window.lkRoomInstance) {
                await window.lkRoomInstance.disconnect();
                window.lkRoomInstance = null;
            }
        };

        window.lk_toggleMic = async () => {
            if (window.lkRoomInstance) {
                const enabled = !window.lkRoomInstance.localParticipant.isMicrophoneEnabled;
                await window.lkRoomInstance.localParticipant.setMicrophoneEnabled(enabled);
                return enabled;
            }
            return true;
        };

        window.lk_toggleCam = async () => {
            if (window.lkRoomInstance) {
                const enabled = !window.lkRoomInstance.localParticipant.isCameraEnabled;
                await window.lkRoomInstance.localParticipant.setCameraEnabled(enabled);
                
                // Refresh local video preview
                const localContainer = document.getElementById('local-video-container');
                if (localContainer) {
                    localContainer.innerHTML = '';
                    if (enabled) {
                        window.lkRoomInstance.localParticipant.videoTrackPublications.forEach(pub => {
                            if (pub.track) {
                                const el = pub.track.attach();
                                el.classList.add('w-full', 'h-full', 'object-cover');
                                localContainer.appendChild(el);
                            }
                        });
                    } else {
                        localContainer.innerHTML = '<div class="absolute inset-0 flex items-center justify-center text-gray-500 text-[10px] uppercase font-bold">Camera Off</div>';
                    }
                }
                return enabled;
            }
            return true;
        };
    </script>
</x-app-layout>
