export class ApiError extends Error {
  code: string
  status: number
  fields?: Record<string, string>

  constructor(code: string, status: number, message: string, fields?: Record<string, string>) {
    super(message)
    this.name = 'ApiError'
    this.code = code
    this.status = status
    this.fields = fields
  }
}

interface ApiOptions {
  method?: 'GET' | 'POST'
  query?: Record<string, string>
  body?: unknown
  csrf?: string
}

interface ApiEnvelope<T> {
  ok: boolean
  data?: T
  error?: { code: string; message: string; fields?: Record<string, string> }
}

export async function api<T = unknown>(action: string, opts: ApiOptions = {}): Promise<T> {
  const params = new URLSearchParams({ action })
  for (const [key, value] of Object.entries(opts.query ?? {})) {
    params.set(key, value)
  }
  const method = opts.method ?? 'GET'
  const headers: Record<string, string> = {}
  let body: string | undefined

  if (method === 'POST') {
    headers['Content-Type'] = 'application/json'
    if (opts.csrf) {
      headers['X-CSRF-Token'] = opts.csrf
    }
    body = JSON.stringify(opts.body ?? {})
  }

  let response: Response
  try {
    response = await fetch(`/api.php?${params.toString()}`, {
      method,
      headers,
      body,
      credentials: 'same-origin',
    })
  } catch {
    throw new ApiError('network_error', 0, '网络错误，请稍后重试')
  }

  let payload: ApiEnvelope<T>
  try {
    payload = (await response.json()) as ApiEnvelope<T>
  } catch {
    throw new ApiError('internal_error', response.status, '服务器响应异常')
  }

  if (response.status === 401 || payload.error?.code === 'not_logged_in') {
    window.dispatchEvent(new CustomEvent('xssp:unauthorized'))
  }

  if (!payload.ok) {
    throw new ApiError(
      payload.error?.code ?? 'internal_error',
      response.status,
      payload.error?.message ?? '请求失败',
      payload.error?.fields,
    )
  }
  return payload.data as T
}
