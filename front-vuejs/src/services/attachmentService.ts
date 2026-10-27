import {
  api,
  multipartConfig,
  parseBlobError,
  type UploadOptions,
} from "./api";
import { filenameFromContentDisposition } from "@/utils/download";

/** Exam attachment of a consultation (Sprint 5). */
export interface Attachment {
  id: number;
  consultation_id: number;
  original_name: string;
  mime_type: string;
  /** Bytes. */
  size: number;
  description: string | null;
  download_url?: string;
  created_at: string;
}

export interface AttachmentDownload {
  blob: Blob;
  filename: string;
}

export const attachmentService = {
  /** Newest first, no pagination. */
  async list(consultationId: number | string): Promise<Attachment[]> {
    const { data } = await api.get<{ data: Attachment[] }>(
      `/consultations/${consultationId}/attachments`
    );
    return data.data;
  },

  async upload(
    consultationId: number | string,
    file: Blob,
    description: string | null,
    options: UploadOptions = {}
  ): Promise<Attachment> {
    const body = new FormData();
    body.append("file", file);
    if (description) body.append("description", description);
    const { data } = await api.post<{ data: Attachment }>(
      `/consultations/${consultationId}/attachments`,
      body,
      multipartConfig(options)
    );
    return data.data;
  },

  /**
   * The original file as a Blob. Requested by id against the configured API
   * (not `download_url`, whose host depends on the server's APP_URL). The
   * filename comes from Content-Disposition when readable, else the original name.
   */
  async download(
    attachment: Pick<Attachment, "id" | "original_name">
  ): Promise<AttachmentDownload> {
    const response = await api
      .get<Blob>(`/attachments/${attachment.id}/download`, {
        responseType: "blob",
        headers: { Accept: "*/*" },
        timeout: 60000,
      })
      .catch(parseBlobError);
    const header = response.headers?.["content-disposition"];
    return {
      blob: response.data,
      filename:
        filenameFromContentDisposition(
          typeof header === "string" ? header : undefined
        ) || attachment.original_name,
    };
  },

  async remove(id: number | string): Promise<void> {
    await api.delete(`/attachments/${id}`);
  },
};
