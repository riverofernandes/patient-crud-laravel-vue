<template>
  <div class="patient-consultations">
    <div class="patient-consultations__toolbar">
      <b-button
        variant="primary"
        :to="{
          name: 'consultations.create',
          params: { id: String(patientId) },
        }"
      >
        <b-icon-calendar-plus aria-hidden="true" class="mr-1" />
        Novo atendimento
      </b-button>
    </div>

    <div v-if="loading" class="state-box" role="status">
      <b-spinner variant="primary" class="mb-2" />
      <p class="mb-0">Carregando atendimentos...</p>
    </div>

    <div v-else-if="error" class="state-box" role="alert">
      <p class="state-box__title">Não foi possível carregar os atendimentos</p>
      <p class="mb-3">{{ error }}</p>
      <b-button variant="primary" @click="load">Tentar novamente</b-button>
    </div>

    <div v-else-if="items.length === 0" class="state-box">
      <p class="state-box__title">Nenhum atendimento registrado</p>
      <p class="mb-0">Use “Novo atendimento” para agendar o primeiro.</p>
    </div>

    <template v-else>
      <ul class="consultation-list" aria-label="Atendimentos do paciente">
        <li
          v-for="item in items"
          :key="item.id"
          class="consultation-item"
          :data-consultation-id="item.id"
          tabindex="-1"
        >
          <div class="consultation-item__when">
            <span class="consultation-item__date">{{ dateOf(item) }}</span>
            <span class="consultation-item__time">{{ rangeOf(item) }}</span>
          </div>
          <div class="consultation-item__body">
            <p class="consultation-item__title">
              <router-link
                :to="{
                  name: 'consultations.show',
                  params: { id: String(item.id) },
                }"
                class="consultation-item__link"
              >
                <span class="sr-only">Atendimento de {{ dateOf(item) }}, </span>
                {{ item.professional_name }}
              </router-link>
              <span v-if="item.professional_crm" class="consultation-item__crm">
                CRM {{ item.professional_crm }}
              </span>
            </p>
            <p v-if="item.reason" class="consultation-item__reason">
              {{ excerpt(item.reason) }}
            </p>
          </div>
          <div class="consultation-item__meta">
            <StatusBadge :status="item.status" sr-prefix="Status:" />
            <span v-if="item.eye_exam" class="exam-flag">
              <b-icon-clipboard-check aria-hidden="true" />
              Prontuário registrado
            </span>
          </div>
        </li>
      </ul>

      <div class="list-footer">
        <p class="list-footer__info" aria-live="polite">
          Mostrando {{ items.length }} de {{ total }}
          {{ total === 1 ? "atendimento" : "atendimentos" }}
        </p>
        <b-button
          v-if="hasMore"
          variant="outline-primary"
          :aria-disabled="loadingMore ? 'true' : null"
          @click="loadMore"
        >
          <b-spinner v-if="loadingMore" small class="mr-1" aria-hidden="true" />
          {{ loadingMore ? "Carregando..." : "Carregar mais" }}
        </b-button>
      </div>
    </template>
  </div>
</template>

<script lang="ts">
import { defineComponent } from "vue";
import StatusBadge from "@/components/StatusBadge.vue";
import {
  consultationService,
  type Consultation,
} from "@/services/consultationService";
import type { PaginationMeta } from "@/services/patientService";
import { getErrorMessage } from "@/services/api";
import { notify } from "@/utils/notify";
import { datePart, formatDate, timeRange } from "@/utils/datetime";

export const CONSULTATIONS_PER_PAGE = 10;
const EXCERPT_LENGTH = 120;

/** "Atendimentos" tab of the patient page: newest first, "Carregar mais". */
export default defineComponent({
  name: "PatientConsultations",
  components: { StatusBadge },
  props: {
    patientId: { type: [String, Number], required: true },
  },
  data() {
    return {
      items: [] as Consultation[],
      meta: null as PaginationMeta | null,
      loading: false,
      loadingMore: false,
      error: "",
    };
  },
  computed: {
    total(): number {
      return this.meta?.total ?? this.items.length;
    },
    hasMore(): boolean {
      return !!this.meta && this.meta.current_page < this.meta.last_page;
    },
  },
  watch: {
    patientId() {
      this.load();
    },
  },
  created() {
    this.load();
  },
  methods: {
    dateOf(item: Consultation): string {
      return formatDate(datePart(item.scheduled_at));
    },
    rangeOf(item: Consultation): string {
      return timeRange(item.scheduled_at, item.duration_minutes);
    },
    excerpt(text: string): string {
      const flat = text.replace(/\s+/g, " ").trim();
      return flat.length > EXCERPT_LENGTH
        ? `${flat.slice(0, EXCERPT_LENGTH - 1).trimEnd()}…`
        : flat;
    },
    async load(): Promise<void> {
      this.loading = true;
      this.error = "";
      try {
        const result = await consultationService.listByPatient(this.patientId, {
          page: 1,
          per_page: CONSULTATIONS_PER_PAGE,
        });
        this.items = result.data;
        this.meta = result.meta;
      } catch (e) {
        this.error = getErrorMessage(e, "Tente novamente em instantes.");
      } finally {
        this.loading = false;
      }
    },
    async loadMore(): Promise<void> {
      if (this.loadingMore || !this.meta) return;
      this.loadingMore = true;
      try {
        const result = await consultationService.listByPatient(this.patientId, {
          page: this.meta.current_page + 1,
          per_page: CONSULTATIONS_PER_PAGE,
        });
        const seen = new Set(this.items.map((c) => c.id));
        const fresh = result.data.filter((c) => !seen.has(c.id));
        this.items = [...this.items, ...fresh];
        this.meta = result.meta;
        if (!this.hasMore && fresh.length) {
          this.$nextTick(() => {
            this.$el
              .querySelector<HTMLElement>(
                `[data-consultation-id="${fresh[0].id}"]`
              )
              ?.focus();
          });
        }
      } catch (e) {
        notify(
          this.$root,
          "error",
          getErrorMessage(e, "Não foi possível carregar mais atendimentos.")
        );
      } finally {
        this.loadingMore = false;
      }
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.patient-consultations {
  padding-top: 24px;
}

.patient-consultations__toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 16px;
}

.consultation-list {
  margin: 0 0 16px;
  padding: 0;
  list-style: none;
  border: 1px solid $border-light;
  border-radius: $border-radius-lg;
}

.consultation-item {
  position: relative;
  display: grid;
  grid-template-columns: 130px minmax(0, 1fr) auto;
  gap: 8px 20px;
  align-items: center;
  padding: 14px 16px;

  & + & {
    border-top: 1px solid $border-light;
  }

  &:hover {
    background: rgba($page-bg, 0.7);
  }

  &:focus {
    outline: 2px solid $blue;
    outline-offset: -2px;
  }
}

.consultation-item__when {
  display: flex;
  flex-direction: column;
  font-variant-numeric: tabular-nums;
}

.consultation-item__date {
  color: $navy;
  font-weight: 800;
}

.consultation-item__time {
  color: $text-muted;
  font-size: 14px;
}

.consultation-item__body {
  min-width: 0;
}

.consultation-item__title {
  display: flex;
  flex-wrap: wrap;
  gap: 2px 10px;
  align-items: baseline;
  margin: 0;
}

.consultation-item__link {
  font-weight: 700;

  // Whole row clickable, link stays the single focus stop.
  &::after {
    position: absolute;
    inset: 0;
    content: "";
  }
}

.consultation-item__crm {
  color: $text-muted;
  font-size: 13px;
}

.consultation-item__reason {
  margin: 2px 0 0;
  overflow: hidden;
  color: $text;
  font-size: 14px;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.consultation-item__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 10px;
  align-items: center;
  justify-content: flex-end;
}

.exam-flag {
  display: inline-flex;
  gap: 6px;
  align-items: center;
  color: #0b6b3c;
  font-size: 13px;
  font-weight: 700;
}

.list-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
}

.list-footer__info {
  margin: 0;
  color: $text-muted;
  font-size: 14px;
}

@media (max-width: 767.98px) {
  .consultation-item {
    grid-template-columns: minmax(0, 1fr);
  }

  .consultation-item__when {
    flex-direction: row;
    gap: 8px;
    align-items: baseline;
  }

  .consultation-item__meta {
    justify-content: flex-start;
  }

  .consultation-item__reason {
    white-space: normal;
  }
}

@media (max-width: 575.98px) {
  .patient-consultations__toolbar .btn {
    width: 100%;
  }
}
</style>
