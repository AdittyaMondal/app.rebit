import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { PostType } from '@/kernel/enums/post/post.type.js';

const usePostEditorStore = defineStore('mobile_post_editor_store', {
    state: function() {
		return {
            draftPost: {},
            quotedPost: null,
            mentionName: null,
            initialType: PostType.TEXT
		}
	},
    getters: {
        pollChoices: (state) => {
            return state.draftPost.relations.poll.choices;
        }
    },
    actions: {
        fetchDraftPost: async function() {
            let state = this;

            // Preserve the locally-typed content before fetching from server
            const localContent = state.draftPost?.content || '';

            await colibriAPI().postEditor().params({
                quoted_post_id: this.quotePostId
            }).getFrom('draft').then((response) => {
                if (response.data.data.draft) {
                    state.draftPost = response.data.data.draft;

                    // Restore local content if the server draft has no content
                    if (!state.draftPost.content && localContent) {
                        state.draftPost.content = localContent;
                    }
                }
                else {
                    state.draftPost = this.getDraftPostDefaultValue();

                    if (localContent) {
                        state.draftPost.content = localContent;
                    }
                }
            }).catch((response) => {
                state.draftPost = this.getDraftPostDefaultValue();

                if (localContent) {
                    state.draftPost.content = localContent;
                }
            }); 
        },
        pollHasChoices: function() {
            return (this.draftPost?.relations?.poll?.choices?.length > 0);
        },
        setPollChoices: function(choicesArr) {
            this.draftPost.relations.poll.choices = choicesArr;
        },
        resetDraftPost: function() {
            this.draftPost = this.getDraftPostDefaultValue();
        },
        setDraftPost: function(postData) {
            this.draftPost = postData;
        },
        finishEditing: function() {
            this.initialType = PostType.TEXT;
            this.mentionName = null;
            this.resetDraftPost();
        },
        getDraftPostDefaultValue: function() {
            let content = '';

            if(this.mentionName) {
                content = `@${this.mentionName} `;
            }

            return {
                content: content,
                type: PostType.TEXT,
                relations: {}
            };
        }
    }
});

export { usePostEditorStore };