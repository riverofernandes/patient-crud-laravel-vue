<template>
  <section class="app-card attachments" aria-labelledby="anexos-title">
    <header class="attachments__header">
      <h2 id="anexos-title" class="section-title" tabindex="-1">
        <b-icon-paperclip aria-hidden="true" class="mr-2" />
        Anexos
      </h2>
    </header>

    <form class="attachment-upload" novalidate @submit.prevent="upload">
      <FormField id="attachment-file" label="Arquivo" :error="fileError">
        <!-- The label is the drop zone; the real (visually hidden) file input
             inside it keeps keyboard and screen reader access: one tab stop. -->
        <label
          class="dropzone"
          :class="{
            'is-dragover': dragover,
            'is-invalid': !!fileError,
            'is-disabled': uploading,
          }"
          @dragenter.prevent="dragover = true"
          @dragover.prevent="dragover = true"
          @dragleave.prevent="dragover = false"
          @drop.prevent="onDrop"
        >
          <input
            id="attachment-file"
            ref="file"
            type="file"
            class="dropzone__input"
            :accept="accept"
            :disabled="uploading"
            :aria-describedby="fileDescribedBy"
            :aria-invalid="fileError ? 'true' : undefined"
            @change="onFileChange"
          />
          <b-icon-cloud-upload class="dropzone__icon" aria-hidden="true" />
          <!-- Visual hint only: the field is already named by its "Arquivo" label. -->
          <span class="dropzone__text" aria-hidden="true">
            <strong>Selecionar arquivo</strong> ou arraste e solte aqui
          </span>
        </label>
        <p id="attachment-file-help" class="dropzone__help">
          PDF, JPEG ou PNG, até 10 MB.
        </p>
        <p
          v-if="file"
          id="attachment-file-selected"
          class="attachment-selected"
        >
          <component
            :is="kindIcon(file.type)"
            aria-hidden="true"
            class="mr-1"
          />
          <span class="sr-only">Arquivo selecionado: </span>
          <span class="attachment-selected__name">{{ file.name }}</span>
          <span class="attachment-selected__size">{{
            formatFileSize(file.size)
          }}</span>
          <button
            type="button"
            class="btn-link-danger attachment-selected__clear"
            :disabled="uploading"
            @click="clearFile"
          >
            Remover arquivo selecionado
          </button>
        </p>
      </FormField>

      <FormField
        id="attachment-description"
        label="Descrição"
        optional
        :error="descriptionError"
      >
        <b-form-input
          id="attachment-description"
          v-model="description"
          placeholder="Ex.: Topografia de córnea"
          maxlength="255"
          :disabled="uploading"
          :state="descriptionError ? false : null"
          :aria-describedby="
            descriptionError ? 'attachment-description-error' : null
          "
          @input="descriptionError = ''"
        />
      </FormField>

      <UploadProgress
        v-if="uploading"
        class="attachment-upload__progress"
        :value="progress"
        label="Enviando anexo"
      />

      <div class="attachment-upload__actions">
        <b-button type="submit" variant="primary" :disabled="uploading">
          <b-spinner v-if="uploading" small class="mr-1" aria-hidden="true" />
          <b-icon-upload v-else aria-hidden="true" class="mr-1" />
          {{ uploading ? "Enviando..." : "Enviar anexo" }}
        </b-button>
      </div>
    </form>

    <div v-if="loading" class="state-box" role="status">
      <b-spinner variant="primary" class="mb-2" />
      <p class="mb-0">Carregando anexos...</p>
    </div>

    <div v-else-if="error" class="state-box" role="alert">
      <p class="state-box__title">Não foi possível carregar os anexos</p>
      <p class="mb-3">{{ error }}</p>
      <b-button variant="primary" @click="load">Tentar novamente</b-button>
    </div>

    <div v-else-if="items.length === 0" class="state-box attachments__empty">
      <p class="state-box__title">Nenhum anexo</p>
      <p class="mb-0">Os laudos e imagens enviados aparecem aqui.</p>
    </div>

    <ul v-else class="attachment-list" aria-label="Anexos do atendimento">
      <li
        v-for="item in items"
        :key="item.id"
        class="attachment-item"
        :data-attachment-id="item.id"
      >
        <span
          class="attachment-item__icon"
          :class="`attachment-item__icon--${fileKind(item.mime_type)}`"
          aria-hidden="true"
        >
          <component :is="kindIcon(item.mime_type)" />
        </span>
        <div class="attachment-item__body">
          <p class="attachment-item__name">{{ item.original_name }}</p>
          <p v-if="item.description" class="attachment-item__description">
            {{ item.description }}
          </p>
          <p class="attachment-item__meta">
            <span class="sr-only">Tamanho: </span>{{ formatFileSize(item.size)
            }}<span aria-hidden="true">{{ " · " }}</span
            ><span class="sr-only">{{ ", enviado em " }}</span
            ><time :datetime="item.created_at">{{
              formatDateTime(item.created_at)
            }}</time>
          </p>
        </div>
        <div class="attachment-item__actions">
          <b-button
            variant="outline-primary"
            size="sm"
            :aria-label="`Baixar ${item.original_name}`"
            :aria-busy="downloadingId === item.id ? 'true' : null"
            :class="{ 'is-busy': downloadingId === item.id }"
            @click="download(item)"
          >
            <b-spinner
              v-if="downloadingId === item.id"
              small
              class="mr-1"
              aria-hidden="true"
            />
            <b-icon-download v-else aria-hidden="true" class="mr-1" />
            Baixar
          </b-button>
          <b-button
            variant="link"
            size="sm"
            class="attachment-item__delete"
            :aria-label="`Excluir anexo ${item.original_name}`"
            @click="toDelete = item"
          >
            <b-icon-trash aria-hidden="true" class="mr-1" />
            Excluir
          </b-button>
        </div>
      </li>
    </ul>

    <b-modal
      :visible="!!toDelete"
      title="Excluir anexo"
      title-tag="h2"
      header-tag="div"
      footer-tag="div"
      centered
      no-close-on-backdrop
      :no-close-on-esc="deleting"
      :hide-header-close="deleting"
      dialog-class="confirm-delete-modal"
      header-close-label="Fechar"
      :return-focus="returnFocus"
      @hide="onHideDelete"
      @hidden="onDeleteHidden"
    >
      <template #modal-header-close>
        <b-icon-x aria-hidden="true" />
      </template>
      <p v-if="toDelete" class="mb-0">
        Deseja realmente excluir o anexo
        <strong>{{ toDelete.original_name }}</strong
        >? Esta ação não pode ser desfeita.
      </p>
      <template #modal-footer>
        <button
          type="button"
          class="btn-link-danger"
          :disabled="deleting"
          @click="toDelete = null"
        >
          Cancelar
        </button>
        <b-button variant="primary" :disabled="deleting" @click="confirmDelete">
          <b-spinner v-if="deleting" small class="mr-1" aria-hidden="true" />
          Confirmar
        </b-button>
      </template>
    </b-modal>
  </section>
</template>

<script lang="ts">
import { defineComponent } from "vue";
import type { BvModalEvent } from "bootstrap-vue";
import FormField from "@/components/FormField.vue";
import UploadProgress from "@/components/UploadProgress.vue";
import {
  attachmentService,
  type Attachment,
} from "@/services/attachmentService";
import {
  getErrorMessage,
  getValidationErrors,
  isNotFound,
} from "@/services/api";
import { notify } from "@/utils/notify";
import { saveBlob } from "@/utils/download";
import { formatDateTime } from "@/utils/format";
import {
  ATTACHMENT_ACCEPT,
  fileKind,
  formatFileSize,
  validateAttachment,
} from "@/utils/files";

export const ATTACHMENT_UPLOADED = "Anexo enviado com sucesso!";
export const ATTACHMENT_DELETED = "Anexo excluído com sucesso!";

const KIND_ICONS: Record<string, string> = {
  pdf: "b-icon-file-earmark-pdf",
  image: "b-icon-file-earmark-image",
  other: "b-icon-file-earmark",
};

/** Exam attachments of a consultation: upload, list, download and delete. */
export default defineComponent({
  name: "ConsultationAttachments",
  components: { FormField, UploadProgress },
  props: {
    consultationId: { type: [Number, String], required: true },
  },
  data() {
    return {
      accept: ATTACHMENT_ACCEPT,
      items: [] as Attachment[],
      loading: true,
      error: "",
      file: null as File | null,
      description: "",
      fileError: "",
      descriptionError: "",
      dragover: false,
      uploading: false,
      progress: 0,
      downloadingId: null as number | null,
      toDelete: null as Attachment | null,
      deleting: false,
      // null = BootstrapVue returns focus to the button that opened the modal.
      returnFocus: null as string | null,
    };
  },
  computed: {
    fileDescribedBy(): string {
      return [
        "attachment-file-help",
        this.file ? "attachment-file-selected" : "",
        this.fileError ? "attachment-file-error" : "",
      ]
        .filter(Boolean)
        .join(" ");
    },
  },
  watch: {
    consultationId: { immediate: true, handler: "load" },
  },
  methods: {
    formatFileSize,
    formatDateTime,
    fileKind,
    kindIcon(mimeType: string): string {
      return KIND_ICONS[fileKind(mimeType)];
    },
    async load(): Promise<void> {
      this.loading = true;
      this.error = "";
      try {
        this.items = await attachmentService.list(this.consultationId);
      } catch (e) {
        this.error = getErrorMessage(e, "Não foi possível carregar os anexos.");
      } finally {
        this.loading = false;
      }
    },
    select(file: File | undefined | null): void {
      if (!file) return;
      const problem = validateAttachment(file);
      this.fileError = problem;
      this.file = problem ? null : file;
      if (problem) this.resetInput();
    },
    onFileChange(event: Event): void {
      this.select((event.target as HTMLInputElement).files?.[0]);
    },
    onDrop(event: DragEvent): void {
      this.dragover = false;
      if (this.uploading) return;
      this.select(event.dataTransfer?.files?.[0]);
      if (this.file || this.fileError) this.focusInput();
    },
    resetInput(): void {
      const input = this.$refs.file as HTMLInputElement | undefined;
      if (input) input.value = "";
    },
    focusInput(): void {
      (this.$refs.file as HTMLInputElement | undefined)?.focus();
    },
    clearFile(): void {
      this.file = null;
      this.fileError = "";
      this.resetInput();
      this.focusInput();
    },
    async upload(): Promise<void> {
      if (this.uploading) return;
      if (!this.file) {
        this.fileError = this.fileError || "Selecione um arquivo.";
        notify(this.$root, "error", "Selecione um arquivo para enviar.");
        this.focusInput();
        return;
      }
      this.uploading = true;
      this.progress = 0;
      this.fileError = "";
      this.descriptionError = "";
      try {
        const created = await attachmentService.upload(
          this.consultationId,
          this.file,
          this.description.trim() || null,
          { onProgress: (percent) => (this.progress = percent) }
        );
        this.items = [
          created,
          ...this.items.filter((a) => a.id !== created.id),
        ];
        this.file = null;
        this.description = "";
        this.resetInput();
        notify(this.$root, "success", ATTACHMENT_UPLOADED);
      } catch (e) {
        const errors = getValidationErrors(e);
        if (errors) {
          this.fileError = errors.file?.[0] ?? "";
          this.descriptionError = errors.description?.[0] ?? "";
        }
        const message =
          this.fileError ||
          this.descriptionError ||
          getErrorMessage(e, "Não foi possível enviar o anexo.");
        if (!errors) this.fileError = message;
        notify(this.$root, "error", message);
        this.$nextTick(() => {
          const target =
            this.descriptionError && !this.fileError
              ? document.getElementById("attachment-description")
              : (this.$refs.file as HTMLElement | undefined);
          target?.focus();
        });
      } finally {
        this.uploading = false;
      }
    },
    async download(item: Attachment): Promise<void> {
      if (this.downloadingId !== null) return;
      this.downloadingId = item.id;
      try {
        const { blob, filename } = await attachmentService.download(item);
        saveBlob(blob, filename);
      } catch (e) {
        notify(
          this.$root,
          "error",
          isNotFound(e)
            ? "Anexo não encontrado."
            : getErrorMessage(e, "Não foi possível baixar o anexo.")
        );
      } finally {
        this.downloadingId = null;
      }
    },
    onHideDelete(event: BvModalEvent): void {
      if (this.deleting) event.preventDefault();
    },
    onDeleteHidden(): void {
      this.returnFocus = null;
      this.toDelete = null;
    },
    async confirmDelete(): Promise<void> {
      if (!this.toDelete || this.deleting) return;
      const target = this.toDelete;
      this.deleting = true;
      try {
        await attachmentService.remove(target.id);
        notify(this.$root, "success", ATTACHMENT_DELETED);
      } catch (e) {
        if (!isNotFound(e)) {
          this.deleting = false;
          notify(
            this.$root,
            "error",
            getErrorMessage(e, "Não foi possível excluir o anexo.")
          );
          return;
        }
        notify(this.$root, "error", "Este anexo já havia sido excluído.");
      }
      this.deleting = false;
      this.items = this.items.filter((a) => a.id !== target.id);
      // The row (and its button) is gone: focus the section heading instead.
      this.returnFocus = "#anexos-title";
      this.toDelete = null;
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.attachments {
  margin-top: 24px;
}

.attachments__header {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 2px solid $border-light;
}

.attachment-upload {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
  gap: 0 16px;
  align-items: start;
}

.attachment-upload__progress,
.attachment-upload__actions {
  grid-column: 1 / -1;
}

.attachment-upload__progress {
  margin-bottom: 16px;
}

.attachment-upload__actions {
  display: flex;
  justify-content: flex-end;
  padding-bottom: 24px;
  border-bottom: 1px solid $border-light;
}

.dropzone {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: center;
  justify-content: center;
  min-height: 112px;
  margin: 0;
  padding: 16px;
  color: $text;
  text-align: center;
  background: $page-bg;
  border: 2px dashed $border;
  border-radius: $border-radius-lg;
  cursor: pointer;

  &:hover,
  &.is-dragover {
    background: rgba($blue, 0.06);
    border-color: $blue;
  }

  &:focus-within {
    border-color: $blue;
    outline: 2px solid $blue;
    outline-offset: 2px;
  }

  &.is-invalid {
    border-color: $red;
  }

  &.is-disabled {
    cursor: progress;
    opacity: 0.7;
  }
}

// Visually hidden but focusable (the zone shows its focus ring).
.dropzone__input {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  white-space: nowrap;
  clip: rect(0 0 0 0);
  clip-path: inset(50%);
}

.dropzone__icon {
  color: $blue;
  font-size: 28px;
}

.dropzone__text strong {
  color: $blue;
}

.dropzone__help {
  margin: 4px 0 0;
  color: $text-muted;
  font-size: 13px;
}

.attachment-selected {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 8px;
  align-items: center;
  margin: 8px 0 0;
  font-size: 14px;
}

.attachment-selected__name {
  overflow-wrap: anywhere;
  color: $text;
  font-weight: 700;
}

.attachment-selected__size {
  color: $text-muted;
}

.attachment-selected__clear {
  padding: 0;
  font-size: 14px;
}

.attachment-list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.attachment-item {
  display: flex;
  gap: 12px 16px;
  align-items: center;
  padding: 16px 0;
  border-bottom: 1px solid $border-light;

  &:last-child {
    border-bottom: 0;
  }
}

.attachment-item__icon {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  color: $blue;
  font-size: 20px;
  background: $page-bg;
  border-radius: $border-radius;

  &--pdf {
    color: $red-text;
  }
}

.attachment-item__body {
  flex: 1 1 auto;
  min-width: 0;

  p {
    margin: 0;
  }
}

.attachment-item__name {
  overflow-wrap: anywhere;
  color: $navy;
  font-weight: 700;
}

.attachment-item__description {
  overflow-wrap: anywhere;
  color: $text;
}

.attachment-item__meta {
  color: $text-muted;
  font-size: 14px;
}

.attachment-item__actions {
  display: flex;
  flex: 0 0 auto;
  flex-wrap: wrap;
  gap: 8px;

  .is-busy {
    cursor: progress;
  }
}

.attachment-item__delete {
  color: $red-text;
  font-weight: 700;

  &:hover,
  &:focus {
    color: darken($red-text, 10%);
  }
}

.attachments__empty {
  padding-bottom: 24px;
}

@media (max-width: 767.98px) {
  .attachment-upload {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 575.98px) {
  .attachment-item {
    flex-wrap: wrap;
  }

  .attachment-item__actions {
    width: 100%;
    padding-left: 56px;
  }

  .attachment-upload__actions ::v-deep .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
