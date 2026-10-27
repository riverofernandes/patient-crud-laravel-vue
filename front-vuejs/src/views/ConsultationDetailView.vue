<template>
  <section>
    <AppBreadcrumb :items="breadcrumb" />

    <PageState v-if="loading" title="Carregando atendimento..." />

    <PageState
      v-else-if="notFound"
      variant="not-found"
      title="Atendimento não encontrado"
      message="O atendimento que você procura não existe ou o paciente foi excluído."
    >
      <b-button variant="primary" :to="{ name: 'agenda' }"
        >Ir para a agenda</b-button
      >
    </PageState>

    <PageState
      v-else-if="error"
      variant="error"
      title="Algo deu errado"
      :message="error"
    >
      <b-button variant="primary" @click="load">Tentar novamente</b-button>
    </PageState>

    <template v-else-if="consultation">
      <article class="app-card consultation-detail">
        <header class="consultation-detail__header">
          <h1 class="consultation-detail__title">{{ heading }}</h1>
          <StatusBadge
            id="consultation-status"
            :status="consultation.status"
            sr-prefix="Status:"
            class="consultation-detail__status"
          />
        </header>

        <ConsultationForm
          v-if="editing"
          class="consultation-detail__edit"
          title="Editar atendimento"
          heading-tag="h2"
          heading-class="section-title"
          submit-label="Salvar alterações"
          :consultation="consultation"
          :busy="saving"
          :server-errors="serverErrors"
          @submit="save"
          @cancel="cancelEdit"
        />

        <template v-else>
          <dl class="consultation-summary">
            <div class="consultation-summary__item">
              <dt>Paciente</dt>
              <dd>
                <router-link
                  v-if="patientId"
                  :to="{ name: 'patients.show', params: { id: patientId } }"
                  >{{ patientName }}</router-link
                >
                <template v-else>{{ patientName }}</template>
              </dd>
            </div>
            <div class="consultation-summary__item">
              <dt>Data</dt>
              <dd>{{ dateLabel }}</dd>
            </div>
            <div class="consultation-summary__item">
              <dt>Horário</dt>
              <dd>
                {{ timeLabel }}
                <span class="consultation-summary__muted"
                  >({{ consultation.duration_minutes }} min)</span
                >
              </dd>
            </div>
            <div class="consultation-summary__item">
              <dt>Profissional</dt>
              <dd>{{ consultation.professional_name }}</dd>
            </div>
            <div class="consultation-summary__item">
              <dt>CRM</dt>
              <dd :class="{ 'is-empty': !consultation.professional_crm }">
                {{ consultation.professional_crm || "Não informado" }}
              </dd>
            </div>
          </dl>
          <dl class="consultation-summary__texts">
            <DetailItem
              label="Queixa principal"
              :value="consultation.reason"
              multiline
            />
            <DetailItem
              label="Anotações"
              :value="consultation.notes"
              multiline
            />
          </dl>

          <footer class="consultation-detail__footer">
            <ConsultationStatusActions
              :consultation="consultation"
              focus-target="#consultation-status"
              @changed="onStatusChanged"
              @stale="load"
            />
            <b-button
              ref="editButton"
              variant="outline-primary"
              class="consultation-detail__edit-btn"
              @click="startEdit"
            >
              <b-icon-pencil aria-hidden="true" class="mr-1" />
              Editar atendimento
            </b-button>
          </footer>
        </template>
      </article>

      <section
        v-if="consultation.status === 'completed'"
        class="app-card prontuario"
        aria-labelledby="prontuario-title"
      >
        <header class="prontuario__header">
          <h2 id="prontuario-title" class="section-title" tabindex="-1">
            <b-icon-file-earmark-medical aria-hidden="true" class="mr-2" />
            Prontuário
          </h2>
          <div v-if="exam && !editingExam" class="prontuario__actions">
            <b-button
              variant="outline-primary"
              :class="{ 'is-busy': downloading }"
              :aria-disabled="downloading ? 'true' : null"
              :aria-busy="downloading ? 'true' : null"
              @click="downloadPrescription"
            >
              <b-spinner
                v-if="downloading"
                small
                class="mr-1"
                aria-hidden="true"
              />
              <b-icon-file-earmark-pdf v-else aria-hidden="true" class="mr-1" />
              {{ downloading ? "Gerando receita..." : "Baixar receita (PDF)" }}
            </b-button>
            <b-button
              ref="editExamButton"
              variant="primary"
              @click="editingExam = true"
            >
              <b-icon-pencil aria-hidden="true" class="mr-1" />
              Editar prontuário
            </b-button>
          </div>
        </header>

        <EyeExamForm
          v-if="!exam || editingExam"
          :consultation-id="consultation.id"
          :exam="exam"
          :cancelable="!!exam"
          @saved="onExamSaved"
          @cancel="cancelExamEdit"
        />
        <EyeExamView v-else :exam="exam" />
      </section>

      <ConsultationAttachments :consultation-id="consultation.id" />
    </template>
  </section>
</template>

<script lang="ts">
import { defineComponent } from "vue";
import AppBreadcrumb, {
  type BreadcrumbItem,
} from "@/components/AppBreadcrumb.vue";
import ConsultationAttachments from "@/components/ConsultationAttachments.vue";
import ConsultationForm from "@/components/ConsultationForm.vue";
import ConsultationStatusActions from "@/components/ConsultationStatusActions.vue";
import DetailItem from "@/components/DetailItem.vue";
import EyeExamForm from "@/components/EyeExamForm.vue";
import EyeExamView from "@/components/EyeExamView.vue";
import PageState from "@/components/PageState.vue";
import StatusBadge from "@/components/StatusBadge.vue";
import {
  consultationService,
  type Consultation,
  type ConsultationPayload,
  type EyeExam,
} from "@/services/consultationService";
import {
  getErrorMessage,
  getValidationErrors,
  isNotFound,
  type ValidationErrors,
} from "@/services/api";
import { notify } from "@/utils/notify";
import { saveBlob } from "@/utils/download";
import { focusHeadingIfParked } from "@/utils/routeFocus";
import {
  datePart,
  formatDate,
  formatDayHeading,
  timeRange,
} from "@/utils/datetime";

function focusEl(el: HTMLElement | null | undefined): void {
  if (!el) return;
  if (!el.hasAttribute("tabindex")) el.setAttribute("tabindex", "-1");
  el.focus({ preventScroll: false });
}

export default defineComponent({
  name: "ConsultationDetailView",
  components: {
    AppBreadcrumb,
    ConsultationAttachments,
    ConsultationForm,
    ConsultationStatusActions,
    DetailItem,
    EyeExamForm,
    EyeExamView,
    PageState,
    StatusBadge,
  },
  props: {
    id: { type: String, required: true },
  },
  data() {
    return {
      consultation: null as Consultation | null,
      loading: true,
      notFound: false,
      error: "",
      editing: false,
      saving: false,
      serverErrors: null as ValidationErrors | null,
      editingExam: false,
      downloading: false,
    };
  },
  computed: {
    exam(): EyeExam | null {
      return this.consultation?.eye_exam ?? null;
    },
    date(): string {
      return datePart(this.consultation?.scheduled_at);
    },
    heading(): string {
      return `Atendimento ${formatDate(this.date)}`;
    },
    dateLabel(): string {
      return formatDayHeading(this.date);
    },
    timeLabel(): string {
      const c = this.consultation;
      return c ? timeRange(c.scheduled_at, c.duration_minutes) : "";
    },
    patientId(): string {
      const c = this.consultation;
      const id = c?.patient?.id ?? c?.patient_id;
      return id ? String(id) : "";
    },
    patientName(): string {
      return this.consultation?.patient?.name ?? "Paciente";
    },
    breadcrumb(): BreadcrumbItem[] {
      const items: BreadcrumbItem[] = [
        { text: "Pacientes", to: { name: "patients.index" } },
      ];
      if (this.consultation) {
        items.push(
          {
            text: this.patientName,
            to: this.patientId
              ? {
                  name: "patients.show",
                  params: { id: this.patientId },
                  query: { aba: "atendimentos" },
                }
              : undefined,
          },
          { text: this.heading }
        );
      } else {
        items.push({ text: "Atendimento" });
      }
      return items;
    },
  },
  watch: {
    id: { immediate: true, handler: "load" },
  },
  methods: {
    async load(): Promise<void> {
      this.loading = !this.consultation;
      this.notFound = false;
      this.error = "";
      try {
        const first = !this.consultation;
        this.consultation = await consultationService.get(this.id);
        if (first) {
          document.title = `${this.heading} | EyeCare`;
          this.$nextTick(focusHeadingIfParked);
        }
      } catch (e) {
        this.consultation = null;
        if (isNotFound(e)) this.notFound = true;
        else
          this.error = getErrorMessage(
            e,
            "Não foi possível carregar o atendimento."
          );
      } finally {
        this.loading = false;
      }
    },
    onStatusChanged(updated: Consultation): void {
      this.consultation = { ...this.consultation, ...updated };
    },
    startEdit(): void {
      this.serverErrors = null;
      this.editing = true;
      this.$nextTick(() =>
        focusEl(
          this.$el.querySelector<HTMLElement>(".consultation-detail__edit h2")
        )
      );
    },
    cancelEdit(): void {
      this.editing = false;
      this.$nextTick(() =>
        focusEl(
          this.$el.querySelector<HTMLElement>(".consultation-detail__edit-btn")
        )
      );
    },
    async save(payload: ConsultationPayload): Promise<void> {
      if (!this.consultation) return;
      this.saving = true;
      this.serverErrors = null;
      try {
        this.consultation = await consultationService.update(
          this.consultation.id,
          payload
        );
        document.title = `${this.heading} | EyeCare`;
        notify(this.$root, "success", "Atendimento atualizado com sucesso!");
        this.editing = false;
        this.$nextTick(() =>
          focusEl(this.$el.querySelector<HTMLElement>("h1"))
        );
      } catch (error) {
        const errors = getValidationErrors(error);
        if (errors) {
          this.serverErrors = errors;
          notify(
            this.$root,
            "error",
            errors.scheduled_at?.[0] ??
              "Verifique os campos destacados para salvar!"
          );
        } else {
          notify(
            this.$root,
            "error",
            getErrorMessage(error, "Não foi possível salvar o atendimento.")
          );
        }
      } finally {
        this.saving = false;
      }
    },
    onExamSaved(updated: Consultation): void {
      this.consultation = { ...this.consultation, ...updated };
      this.editingExam = false;
      this.$nextTick(() =>
        focusEl(this.$el.querySelector<HTMLElement>("#prontuario-title"))
      );
    },
    cancelExamEdit(): void {
      this.editingExam = false;
      this.$nextTick(() =>
        focusEl(this.$el.querySelector<HTMLElement>("#prontuario-title"))
      );
    },
    async downloadPrescription(): Promise<void> {
      if (this.downloading || !this.consultation) return;
      this.downloading = true;
      try {
        const { blob, filename } =
          await consultationService.downloadPrescription(
            this.consultation.id,
            this.date
          );
        saveBlob(blob, filename);
        notify(this.$root, "success", `Receita baixada: ${filename}`);
      } catch (e) {
        notify(
          this.$root,
          "error",
          getErrorMessage(e, "Não foi possível gerar a receita.")
        );
      } finally {
        this.downloading = false;
      }
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.consultation-detail__header {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 16px;
  align-items: center;
  padding: 4px 0 24px;
  border-bottom: 2px solid $border-light;
}

.consultation-detail__title {
  margin: 0;
  font-size: 24px;
}

.consultation-detail__status:focus {
  outline: 2px solid $blue;
  outline-offset: 2px;
}

.consultation-summary {
  display: grid;
  grid-template-columns:
    minmax(0, 1.4fr) minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1.4fr)
    minmax(0, 1fr);
  gap: 16px;
  margin: 0;
  padding: 24px 0 8px;
}

.consultation-summary__item {
  min-width: 0;

  dt {
    margin-bottom: 6px;
    color: $navy;
    font-size: 14px;
    font-weight: 600;
  }

  dd {
    margin: 0;
    overflow-wrap: anywhere;
    color: $text;
    font-size: 16px;
    font-weight: 700;

    &.is-empty {
      color: $text-muted;
      font-style: italic;
      font-weight: 400;
    }
  }
}

.consultation-summary__muted {
  color: $text-muted;
  font-weight: 400;
}

.consultation-summary__texts {
  margin: 16px 0 0;
}

.consultation-detail__footer {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: center;
  justify-content: space-between;
  padding-top: 16px;
  border-top: 1px solid $border-light;
}

.prontuario {
  margin-top: 24px;
}

.prontuario__header {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 16px;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 2px solid $border-light;
}

.prontuario__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;

  .is-busy {
    cursor: progress;
  }
}

::v-deep .section-title {
  display: flex;
  align-items: center;
  margin: 0;
  font-size: 20px;
  line-height: 28px;

  &:focus {
    outline: none;
  }
}

@media (max-width: 1099.98px) {
  .consultation-summary {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 575.98px) {
  .consultation-summary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .consultation-detail__footer,
  .prontuario__actions {
    flex-direction: column;
    align-items: stretch;

    ::v-deep .btn {
      justify-content: center;
    }
  }
}
</style>
