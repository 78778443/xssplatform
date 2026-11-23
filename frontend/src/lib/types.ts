export interface ProjectSummary {
  id: number
  title: string
  description: string
  contentNum: number
  addTime: number
}

export interface ModuleSummary {
  id: number
  title: string
  description: string
  isOpen: number
  isAudit: number
  userId: number
}

export interface DashboardData {
  projects: ProjectSummary[]
  modules: ModuleSummary[]
}

export interface ProjectDetail {
  id: number
  title: string
  description: string
  code: string
  urlKey: string
  authCode: string
  modules: string
  moduleSetKeys: string
}

export interface ContentItem {
  id: number
  content: Record<string, string>
  serverContent: Record<string, string>
  domain: string
  addTime: number
}

export interface ProjectViewData {
  project: ProjectDetail
  contents: ContentItem[]
  domains: string[]
  domain: string
}

export interface SetkeyValue {
  key: string
  value?: string
}

export interface ModuleForProject {
  id: number
  title: string
  description: string
  code: string
  keys: string
  setkeys?: SetkeyValue[]
  choosed?: number
}

export interface ProjectConfig {
  project: ProjectDetail
  modulesCan: ModuleForProject[]
  ty: string
}

export interface ProjectCode {
  code: string
  codeurl: string
  shortShow1: string
  shortShow3: string
  scriptShow1: string
  scriptShow2: string
  ty: string
}

export interface ModuleDetail {
  id: number
  title: string
  description: string
  code: string
  isOpen: number
  isAudit: number
  userId: number
}

export interface ModuleGetData {
  module: ModuleDetail
  keys: string[]
  setkeys: string[]
  canEdit: boolean
}
