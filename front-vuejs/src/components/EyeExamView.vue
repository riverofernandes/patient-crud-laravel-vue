<template>
  <div class="eye-exam-view">
    <table class="eye-table">
      <caption class="sr-only">
        Exame por olho: olho direito (OD) e olho esquerdo (OE)
      </caption>
      <thead>
        <tr>
          <th scope="col"><span class="sr-only">Medida</span></th>
          <th v-for="eye in eyes" :key="eye" scope="col">
            <abbr :title="eyeLabels[eye].long">{{ eyeLabels[eye].short }}</abbr>
            <span class="eye-table__eye-name">{{ eyeLabels[eye].long }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.key">
          <th scope="row">{{ row.label }}</th>
          <td
            v-for="eye in eyes"
            :key="eye"
            :class="{ 'is-empty': !row.value(eye) }"
          >
            {{ row.value(eye) || "—" }}
          </td>
        </tr>
      </tbody>
    </table>

    <dl class="eye-exam-view__texts">
      <DetailItem label="Diagnóstico" :value="exam.diagnosis" multiline />
      <DetailItem label="Observações" :value="exam.observations" multiline />
    </dl>
    <p v-if="exam.updated_at" class="eye-exam-view__updated">
      Atualizado em {{ formatDateTimeSp(exam.updated_at) }}
    </p>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";
import DetailItem from "@/components/DetailItem.vue";
import type { Eye, EyeExam } from "@/services/consultationService";
import { EYE_LABELS, EYES, formatDiopter, toNumber } from "@/utils/eyeExam";
import { formatDateTimeSp } from "@/utils/datetime";

/** Read-only prontuário: OD/OE table (fields as rows: fits on mobile). */
export default defineComponent({
  name: "EyeExamView",
  components: { DetailItem },
  props: {
    exam: { type: Object as PropType<EyeExam>, required: true },
  },
  data() {
    return { eyes: EYES, eyeLabels: EYE_LABELS };
  },
  computed: {
    rows(): { key: string; label: string; value: (eye: Eye) => string }[] {
      const exam = this.exam;
      const int = (v: unknown, suffix = "") => {
        const n = toNumber(v);
        return n === null ? "" : `${Math.round(n)}${suffix}`;
      };
      return [
        {
          key: "sc",
          label: "Acuidade sem correção",
          value: (eye) => exam[eye]?.visual_acuity_sc ?? "",
        },
        {
          key: "cc",
          label: "Acuidade com correção",
          value: (eye) => exam[eye]?.visual_acuity_cc ?? "",
        },
        {
          key: "spherical",
          label: "Esférico",
          value: (eye) => formatDiopter(exam[eye]?.spherical),
        },
        {
          key: "cylindrical",
          label: "Cilíndrico",
          value: (eye) => formatDiopter(exam[eye]?.cylindrical),
        },
        {
          key: "axis",
          label: "Eixo",
          value: (eye) => int(exam[eye]?.axis, "°"),
        },
        {
          key: "addition",
          label: "Adição",
          value: (eye) => formatDiopter(exam[eye]?.addition),
        },
        {
          key: "iop",
          label: "PIO",
          value: (eye) => int(exam[eye]?.iop, " mmHg"),
        },
      ];
    },
  },
  methods: { formatDateTimeSp },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.eye-table {
  width: 100%;
  max-width: 640px;
  margin-bottom: 24px;
  font-variant-numeric: tabular-nums;
  border-collapse: separate;
  border-spacing: 0;
  border: 1px solid $border-light;
  border-radius: $border-radius-lg;

  th,
  td {
    padding: 10px 14px;
    font-size: 15px;
    border-bottom: 1px solid $border-light;
  }

  tbody tr:last-child th,
  tbody tr:last-child td {
    border-bottom: 0;
  }

  thead th {
    color: $navy;
    font-weight: 800;
    background: $page-bg;
  }

  thead th:first-child {
    border-top-left-radius: $border-radius-lg;
  }

  thead th:last-child {
    border-top-right-radius: $border-radius-lg;
  }

  tbody th {
    color: $navy;
    font-weight: 600;
  }

  td {
    color: $text;
    font-weight: 700;
    text-align: center;

    &.is-empty {
      color: $text-muted;
      font-weight: 400;
    }
  }

  thead th:not(:first-child) {
    text-align: center;
  }

  abbr {
    text-decoration: none;
    cursor: help;
  }
}

.eye-table__eye-name {
  display: block;
  color: $text-muted;
  font-size: 12px;
  font-weight: 600;
}

.eye-exam-view__texts {
  margin: 0;
}

.eye-exam-view__updated {
  margin: -8px 0 0;
  color: $text-muted;
  font-size: 14px;
}

@media (max-width: 575.98px) {
  .eye-table {
    th,
    td {
      padding: 8px 10px;
      font-size: 14px;
    }
  }
}
</style>
