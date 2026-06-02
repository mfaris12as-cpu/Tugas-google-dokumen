<script setup lang="ts">
import { Head, Link, useHttp } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
    avatar_url: string;
}

interface DocumentData {
    id: number;
    title: string;
    content: string;
    user_id: number | null;
    user: User | null;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    document: DocumentData;
    auth: {
        user: User;
    };
}>();

const title = ref(props.document.title);
const content = ref(props.document.content || '');
const textareaRef = ref<HTMLTextAreaElement | null>(null);

// Active users in the presence channel
const activeUsers = ref<User[]>([]);

// Remote cursors state
interface RemoteCursor {
    userId: number;
    name: string;
    color: string;
    start: number;
    end: number;
    top: number;
    left: number;
}
const remoteCursors = ref<Record<number, RemoteCursor>>({});

// Deterministic colors for collaborators
const colors = [
    '#EF4444', // Red
    '#F59E0B', // Amber
    '#10B981', // Emerald
    '#3B82F6', // Blue
    '#8B5CF6', // Violet
    '#EC4899', // Pink
    '#14B8A6', // Teal
    '#F43F5E', // Rose
];

const getUserColor = (userId: number) => {
    return colors[userId % colors.length];
};

// Use HTTP for background autosaving
const http = useHttp({
    title: props.document.title,
    content: props.document.content || '',
});

// Save status indicator
const isDirty = ref(false);
const saveStatus = computed<'saved' | 'saving' | 'error'>(() => {
    if (http.processing || isDirty.value) {
        return 'saving';
    }
    if (Object.keys(http.errors).length > 0) {
        return 'error';
    }
    return 'saved';
});

let saveTimeout: ReturnType<typeof setTimeout> | null = null;

const triggerAutosave = () => {
    isDirty.value = true;
    if (saveTimeout) {
        clearTimeout(saveTimeout);
    }
    saveTimeout = setTimeout(() => {
        http.title = title.value;
        http.content = content.value;
        http.put(`/documents/${props.document.id}`, {
            onFinish: () => {
                isDirty.value = false;
            }
        });
    }, 1000); // 1 second debounce
};

// Laravel Echo reference
let echoChannel: any = null;

// Send local cursor updates to others
const whisperCursor = () => {
    const el = textareaRef.value;
    if (!el || !echoChannel) return;

    echoChannel.whisper('cursor', {
        userId: props.auth.user.id,
        name: props.auth.user.name,
        color: getUserColor(props.auth.user.id),
        start: el.selectionStart,
        end: el.selectionEnd,
    });
};

// When content or title changes locally
const onContentInput = () => {
    triggerAutosave();
    whisperCursor();

    if (echoChannel) {
        echoChannel.whisper('edit', {
            content: content.value,
            senderId: props.auth.user.id,
        });
    }
};

const onTitleInput = () => {
    triggerAutosave();
    if (echoChannel) {
        echoChannel.whisper('edit', {
            title: title.value,
            senderId: props.auth.user.id,
        });
    }
};

// Calculate screen coordinates for a remote cursor
const updateRemoteCursorCoordinates = (userId: number) => {
    const cursor = remoteCursors.value[userId];
    if (!cursor) return;

    const el = textareaRef.value;
    if (!el) return;

    // Create a mirror element to measure text boundaries
    let mirror = document.getElementById('textarea-mirror');
    if (!mirror) {
        mirror = document.createElement('div');
        mirror.id = 'textarea-mirror';
        document.body.appendChild(mirror);
    }

    const styles = window.getComputedStyle(el);
    mirror.style.position = 'absolute';
    mirror.style.visibility = 'hidden';
    mirror.style.whiteSpace = 'pre-wrap';
    mirror.style.wordWrap = 'break-word';
    mirror.style.font = styles.font;
    mirror.style.fontSize = styles.fontSize;
    mirror.style.fontFamily = styles.fontFamily;
    mirror.style.lineHeight = styles.lineHeight;
    mirror.style.padding = styles.padding;
    mirror.style.border = styles.border;
    mirror.style.width = el.clientWidth + 'px';
    mirror.style.boxSizing = styles.boxSizing;

    // Content up to the selection start
    const text = el.value.substring(0, cursor.start);
    mirror.textContent = text;

    // Add marker span
    const marker = document.createElement('span');
    marker.textContent = '|';
    mirror.appendChild(marker);

    // Get coordinates
    const rect = marker.getBoundingClientRect();
    const mirrorRect = mirror.getBoundingClientRect();

    const relativeTop = rect.top - mirrorRect.top;
    const relativeLeft = rect.left - mirrorRect.left;

    // Adjust for current scroll of the textarea
    cursor.top = relativeTop - el.scrollTop;
    cursor.left = relativeLeft - el.scrollLeft;

    // Reset mirror text to free memory
    mirror.textContent = '';
};

// Recalculate all remote cursors (e.g. on scroll or resize)
const recalculateAllRemoteCursors = () => {
    Object.keys(remoteCursors.value).forEach((userId) => {
        updateRemoteCursorCoordinates(parseInt(userId));
    });
};

onMounted(() => {
    // Join the presence channel for this document
    const channelName = `document.${props.document.id}`;
    
    // Check if Echo is available
    if (window.Echo) {
        echoChannel = window.Echo.join(channelName)
            .here((users: User[]) => {
                activeUsers.value = users;
            })
            .joining((user: User) => {
                if (!activeUsers.value.find(u => u.id === user.id)) {
                    activeUsers.value.push(user);
                }
            })
            .leaving((user: User) => {
                activeUsers.value = activeUsers.value.filter(u => u.id !== user.id);
                delete remoteCursors.value[user.id];
            })
            .listenForWhisper('edit', (e: any) => {
                if (e.senderId === props.auth.user.id) return;

                // Sync title if updated
                if (e.title !== undefined && e.title !== title.value) {
                    title.value = e.title;
                }

                // Sync content if updated, keeping the local selection intact
                if (e.content !== undefined && e.content !== content.value) {
                    const el = textareaRef.value;
                    if (el) {
                        const start = el.selectionStart;
                        const end = el.selectionEnd;

                        content.value = e.content;

                        nextTick(() => {
                            el.setSelectionRange(start, end);
                            recalculateAllRemoteCursors();
                        });
                    } else {
                        content.value = e.content;
                    }
                }
            })
            .listenForWhisper('cursor', (e: any) => {
                if (e.userId === props.auth.user.id) return;

                remoteCursors.value[e.userId] = {
                    userId: e.userId,
                    name: e.name,
                    color: e.color,
                    start: e.start,
                    end: e.end,
                    top: 0,
                    left: 0,
                };

                // Calculate coordinates
                nextTick(() => {
                    updateRemoteCursorCoordinates(e.userId);
                });
            });
    }

    // Attach resize listener to keep cursors aligned
    window.addEventListener('resize', recalculateAllRemoteCursors);
});

onUnmounted(() => {
    if (echoChannel && window.Echo) {
        window.Echo.leave(`document.${props.document.id}`);
    }
    window.removeEventListener('resize', recalculateAllRemoteCursors);
    
    // Clean up mirror element if any
    const mirror = document.getElementById('textarea-mirror');
    if (mirror) {
        mirror.remove();
    }
});

// Compute cursors that are currently visible (inside viewport of textarea)
const visibleCursors = computed(() => {
    return Object.values(remoteCursors.value).filter(cursor => {
        const el = textareaRef.value;
        if (!el) return false;
        // Basic boundaries check
        return cursor.top >= 0 && cursor.top <= el.clientHeight;
    });
});
</script>

<template>
    <Head :title="`${title || 'Untitled'} - Collabify`" />
    <div class="min-h-screen bg-slate-50 flex flex-col">
        <!-- Top Editor Navbar -->
        <header class="bg-white shadow-sm border-b border-slate-200 px-4 py-3 sm:px-6">
            <div class="max-w-7xl mx-auto flex flex-col space-y-3 md:flex-row md:items-center md:justify-between md:space-y-0">
                
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 flex-1 min-w-0">
                    <Link
                        href="/"
                        class="text-indigo-600 hover:text-indigo-800 font-medium text-sm flex items-center space-x-1 shrink-0"
                    >
                        <span>← Dashboard</span>
                    </Link>
                    <span class="text-slate-300">|</span>
                    
                    <input
                        v-model="title"
                        @input="onTitleInput"
                        type="text"
                        placeholder="Nama Dokumen"
                        class="text-base sm:text-lg font-bold text-slate-800 border-b border-transparent hover:border-slate-300 focus:border-indigo-500 focus:outline-none px-1 py-0.5 transition-colors flex-1 min-w-[120px] max-w-xs sm:max-w-md"
                    />

                    <!-- Saving indicator -->
                    <span class="text-xs select-none shrink-0">
                        <span v-if="saveStatus === 'saving'" class="text-amber-500 font-medium">⚡ Menyimpan...</span>
                        <span v-else-if="saveStatus === 'saved'" class="text-emerald-500 font-medium">✓ Tersimpan ke DB</span>
                        <span v-else class="text-red-500 font-medium">✗ Gagal menyimpan</span>
                    </span>
                </div>

                <!-- Active Collaborators list -->
                <div class="flex items-center space-x-2 shrink-0 justify-between md:justify-end">
                    <span class="text-xs text-slate-500 mr-2">Sedang mengedit:</span>
                    <div class="flex -space-x-1 overflow-hidden">
                        <div
                            v-for="user in activeUsers"
                            :key="user.id"
                            class="inline-block h-8 w-8 rounded-full ring-2 text-xs font-bold text-white flex items-center justify-center select-none overflow-hidden"
                            :style="{
                                ringColor: user.id === auth.user.id ? '#4F46E5' : '#FFFFFF'
                            }"
                            :title="user.name + (user.id === auth.user.id ? ' (Anda)' : '')"
                        >
                            <img
                                v-if="user.avatar_url"
                                :src="user.avatar_url"
                                :alt="user.name"
                                class="h-full w-full object-cover bg-white"
                            />
                            <span
                                v-else
                                class="h-full w-full flex items-center justify-center"
                                :style="{ backgroundColor: getUserColor(user.id) }"
                            >
                                {{ user.name.substring(0, 2).toUpperCase() }}
                            </span>
                        </div>
                    </div>
                </div>

            </div>
        </header>

        <!-- Editor Area -->
        <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 flex flex-col">
            <div class="flex-1 bg-white border border-slate-200 rounded-lg shadow-sm flex flex-col overflow-hidden relative">
                
                <div class="relative flex-1">
                    <!-- Textarea Editor -->
                    <textarea
                        ref="textareaRef"
                        v-model="content"
                        @input="onContentInput"
                        @keyup="whisperCursor"
                        @click="whisperCursor"
                        @focus="whisperCursor"
                        @scroll="recalculateAllRemoteCursors"
                        placeholder="Mulai menulis dokumen di sini..."
                        class="w-full h-full min-h-[500px] p-6 font-mono text-base border-none focus:outline-none focus:ring-0 resize-none leading-relaxed text-slate-800"
                    ></textarea>

                    <!-- Remote Cursors Overlay Container -->
                    <div class="absolute inset-0 pointer-events-none overflow-hidden">
                        <div
                            v-for="cursor in visibleCursors"
                            :key="cursor.userId"
                            class="absolute transition-all duration-75 ease-out"
                            :style="{
                                top: `${cursor.top}px`,
                                left: `${cursor.left}px`
                            }"
                        >
                            <!-- Cursor line caret -->
                            <div class="w-[2px] h-[22px] animate-pulse" :style="{ backgroundColor: cursor.color }"></div>
                            <!-- Cursor label flag -->
                            <div
                                class="absolute bottom-full left-0 px-1 py-0.5 rounded text-[10px] text-white font-bold whitespace-nowrap shadow-sm select-none"
                                :style="{ backgroundColor: cursor.color }"
                            >
                                {{ cursor.name }}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="mt-3 text-xs text-slate-400 flex justify-between">
                <span>Gunakan WiFi yang sama untuk melihat perubahan kursor dan teks secara realtime.</span>
                <span>Karakter: {{ content.length }}</span>
            </div>
        </main>
    </div>
</template>
