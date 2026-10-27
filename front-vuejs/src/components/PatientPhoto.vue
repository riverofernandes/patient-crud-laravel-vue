<template>
  <div class="patient-photo">
    <!-- The visible button opens the picker; the input itself is not rendered. -->
    <input
      ref="file"
      type="file"
      class="d-none"
      :accept="accept"
      tabindex="-1"
      aria-hidden="true"
      @change="onFileChange"
    />
    <div class="patient-photo__actions">
      <b-button
        id="patient-photo-pick"
        variant="outline-primary"
        size="sm"
        class="patient-photo__pick"
        aria-describedby="patient-photo-help"
        @click="pick"
      >
        <b-icon-camera aria-hidden="true" class="mr-1" />
        {{ hasPhoto ? "Trocar foto" : "Adicionar foto" }}
      </b-button>
      <button
        v-if="hasPhoto"
        id="patient-photo-remove"
        type="button"
        class="btn-link-danger patient-photo__remove"
        @click="removing = true"
      >
        Remover foto
      </button>
    </div>
    <p id="patient-photo-help" class="patient-photo__help">
      JPEG, PNG ou WebP, até 2 MB.
    </p>
    <p
      v-if="error"
      id="patient-photo-error"
      class="patient-photo__error"
      role="alert"
    >
      {{ error }}
    </p>

    <b-modal
      :visible="!!selected"
      title="Enviar foto"
      title-tag="h2"
      header-tag="div"
      footer-tag="div"
      centered
      no-close-on-backdrop
      :no-close-on-esc="uploading"
      :hide-header-close="uploading"
      dialog-class="confirm-delete-modal patient-photo-modal"
      header-close-label="Fechar"
      return-focus="#patient-photo-pick"
      @hide="onHide"
      @hidden="clearSelection"
    >
      <template #modal-header-close>
        <b-icon-x aria-hidden="true" />
      </template>

      <div v-if="selected" class="patient-photo__preview">
        <img
          v-if="previewUrl"
          :src="previewUrl"
          alt="Pré-visualização da nova foto"
          class="patient-photo__preview-img"
        />
        <p class="patient-photo__file">
          <span class="patient-photo__file-name">{{ selected.name }}</span>
          <span class="patient-photo__file-size">{{
            formatFileSize(selected.size)
          }}</span>
        </p>
        <p class="patient-photo__note">
          A imagem será recortada no centro em formato quadrado.
        </p>
        <UploadProgress
          v-if="uploading"
          :value="progress"
          label="Enviando foto"
        />
        <p v-if="uploadError" class="patient-photo__error mb-0" role="alert">
          {{ uploadError }}
        </p>
      </div>

      <template #modal-footer>
        <button
          type="button"
          class="btn-link-danger"
          :disabled="uploading"
          @click="clearSelection"
        >
          Cancelar
        </button>
        <b-button variant="primary" :disabled="uploading" @click="upload">
          <b-spinner v-if="uploading" small class="mr-1" aria-hidden="true" />
          {{ uploading ? "Enviando..." : "Enviar foto" }}
        </b-button>
      </template>
    </b-modal>

    <b-modal
      :visible="removing"
      title="Remover foto"
      title-tag="h2"
      header-tag="div"
      footer-tag="div"
      centered
      no-close-on-backdrop
      :no-close-on-esc="busyRemoving"
      :hide-header-close="busyRemoving"
      dialog-class="confirm-delete-modal"
      header-close-label="Fechar"
      return-focus="#patient-photo-pick"
      @hide="onHideRemove"
      @hidden="removing = false"
    >
      <template #modal-header-close>
        <b-icon-x aria-hidden="true" />
      </template>
      <p class="mb-0">
        Deseja realmente remover a foto de <strong>{{ patient.name }}</strong
        >?
      </p>
      <template #modal-footer>
        <button
          type="button"
          class="btn-link-danger"
          :disabled="busyRemoving"
          @click="removing = false"
        >
          Cancelar
        </button>
        <b-button
          variant="primary"
          :disabled="busyRemoving"
          @click="confirmRemove"
        >
          <b-spinner
            v-if="busyRemoving"
            small
            class="mr-1"
            aria-hidden="true"
          />
          Confirmar
        </b-button>
      </template>
    </b-modal>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";
import type { BvModalEvent } from "bootstrap-vue";
import UploadProgress from "@/components/UploadProgress.vue";
import { patientService, type Patient } from "@/services/patientService";
import {
  getErrorMessage,
  getValidationErrors,
  isNotFound,
} from "@/services/api";
import { notify } from "@/utils/notify";
import { PHOTO_ACCEPT, formatFileSize, validatePhoto } from "@/utils/files";

export const PHOTO_UPDATED = "Foto atualizada com sucesso!";
export const PHOTO_REMOVED = "Foto removida com sucesso!";

/**
 * Photo actions of the patient detail page: pick (client checks + preview),
 * upload with progress, and removal behind a confirmation.
 * Emits `updated` with the patient after each change.
 */
export default defineComponent({
  name: "PatientPhoto",
  components: { UploadProgress },
  props: {
    patient: {
      type: Object as PropType<Pick<Patient, "id" | "name" | "photo_url">>,
      required: true,
    },
  },
  data() {
    return {
      accept: PHOTO_ACCEPT,
      selected: null as File | null,
      previewUrl: "",
      error: "",
      uploadError: "",
      uploading: false,
      progress: 0,
      removing: false,
      busyRemoving: false,
    };
  },
  computed: {
    hasPhoto(): boolean {
      return !!this.patient.photo_url;
    },
  },
  beforeDestroy() {
    this.revokePreview();
  },
  methods: {
    formatFileSize,
    pick(): void {
      const input = this.$refs.file as HTMLInputElement | undefined;
      if (!input) return;
      input.value = "";
      input.click();
    },
    onFileChange(event: Event): void {
      const input = event.target as HTMLInputElement;
      const file = input.files?.[0];
      if (!file) return;
      const problem = validatePhoto(file);
      if (problem) {
        this.error = problem;
        input.value = "";
        return;
      }
      this.error = "";
      this.uploadError = "";
      this.progress = 0;
      this.revokePreview();
      this.previewUrl =
        typeof URL.createObjectURL === "function"
          ? URL.createObjectURL(file)
          : "";
      this.selected = file;
    },
    revokePreview(): void {
      if (this.previewUrl && typeof URL.revokeObjectURL === "function")
        URL.revokeObjectURL(this.previewUrl);
      this.previewUrl = "";
    },
    onHide(event: BvModalEvent): void {
      if (this.uploading) event.preventDefault();
      else this.selected = null;
    },
    clearSelection(): void {
      if (this.uploading) return;
      this.selected = null;
      this.uploadError = "";
      this.revokePreview();
      const input = this.$refs.file as HTMLInputElement | undefined;
      if (input) input.value = "";
    },
    async upload(): Promise<void> {
      if (!this.selected || this.uploading) return;
      this.uploading = true;
      this.uploadError = "";
      this.progress = 0;
      try {
        const updated = await patientService.uploadPhoto(
          this.patient.id,
          this.selected,
          { onProgress: (percent) => (this.progress = percent) }
        );
        this.uploading = false;
        this.$emit("updated", updated);
        notify(this.$root, "success", PHOTO_UPDATED);
        this.clearSelection();
      } catch (e) {
        this.uploading = false;
        const errors = getValidationErrors(e);
        this.uploadError =
          errors?.photo?.[0] ??
          getErrorMessage(e, "Não foi possível enviar a foto.");
      }
    },
    onHideRemove(event: BvModalEvent): void {
      if (this.busyRemoving) event.preventDefault();
    },
    async confirmRemove(): Promise<void> {
      if (this.busyRemoving) return;
      this.busyRemoving = true;
      try {
        await patientService.removePhoto(this.patient.id);
        this.finishRemove();
      } catch (e) {
        // Already gone (removed elsewhere): the outcome the user asked for.
        if (isNotFound(e)) {
          this.finishRemove();
          return;
        }
        this.busyRemoving = false;
        notify(
          this.$root,
          "error",
          getErrorMessage(e, "Não foi possível remover a foto.")
        );
      }
    },
    finishRemove(): void {
      this.busyRemoving = false;
      this.removing = false;
      this.error = "";
      this.$emit("updated", { ...this.patient, photo_url: null });
      notify(this.$root, "success", PHOTO_REMOVED);
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.patient-photo__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 16px;
  align-items: center;
}

.patient-photo__pick {
  display: inline-flex;
  align-items: center;
}

.patient-photo__remove {
  font-size: 14px;
}

.patient-photo__help {
  margin: 4px 0 0;
  color: $text-muted;
  font-size: 12px;
}

.patient-photo__error {
  margin: 4px 0 0;
  color: $red-text;
  font-size: 14px;
  font-weight: 600;
}

.patient-photo__preview {
  display: flex;
  flex-direction: column;
  gap: 12px;
  align-items: center;
  text-align: center;
}

.patient-photo__preview-img {
  width: 160px;
  height: 160px;
  object-fit: cover;
  border: 1px solid $border;
  border-radius: 50%;
}

.patient-photo__file {
  display: flex;
  flex-direction: column;
  margin: 0;
}

.patient-photo__file-name {
  overflow-wrap: anywhere;
  color: $text;
  font-weight: 700;
}

.patient-photo__file-size,
.patient-photo__note {
  margin: 0;
  color: $text-muted;
  font-size: 14px;
}

.patient-photo__preview ::v-deep .upload-progress {
  align-self: stretch;
}
</style>
